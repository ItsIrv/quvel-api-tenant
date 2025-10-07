<?php

declare(strict_types=1);

namespace Quvel\Tenant;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Queue\QueueManager;
use Illuminate\Routing\Router;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Quvel\Tenant\Cache\TenantDatabaseStore;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Facades\TenantContext as TenantContextFacade;
use Quvel\Tenant\Managers\TenantTableManager;
use Quvel\Tenant\Http\Middleware\TenantMiddleware;
use Quvel\Tenant\Managers\ConfigurationPipeManager;
use Quvel\Tenant\Managers\TenantResolverManager;
use Quvel\Tenant\Queue\Connectors\TenantDatabaseConnector;
use Quvel\Tenant\Queue\Failed\TenantDatabaseUuidFailedJobProvider;
use Quvel\Tenant\Queue\TenantDatabaseBatchRepository;
use Quvel\Tenant\Session\TenantDatabaseSessionHandler;
use Quvel\Tenant\Auth\TenantPasswordBrokerManager;
use Quvel\Tenant\Concerns\HandlesTenantModels;
use RuntimeException;

class TenantServiceProvider extends ServiceProvider
{
    use HandlesTenantModels;
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/tenant.php',
            'tenant'
        );

        $this->app->singleton(TenantTableManager::class, function () {
            return new TenantTableManager();
        });

        $this->app->singleton(TenantResolverManager::class, function () {
            return new TenantResolverManager();
        });

        $this->app->singleton(TenantResolver::class, function ($app) {
            return $app->make(TenantResolverManager::class)->getResolver();
        });

        $this->app->singleton(ConfigurationPipeManager::class, function () {
            return new ConfigurationPipeManager();
        });

        $this->app->scoped(TenantContext::class, function () {
            return new TenantContext();
        });

        $this->registerTenantQueueConnector();
        $this->registerTenantBatchRepository();
        $this->registerTenantSessionHandler();
        $this->registerTenantCacheStore();
        $this->registerTenantCacheManager();
        $this->registerTenantPasswordResetTokenRepository();
        $this->registerTenantFilesystemManager();
        $this->registerTenantMailManager();
        $this->registerFacades();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerTenantMiddleware();
        $this->registerMiddlewareAlias();
        $this->registerRoutes();
        $this->bootExternalModelScoping();
        $this->registerContextPreservation();
        $this->registerTenantFailedJobProvider();
        $this->registerTenantBroadcastingManager();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tenant.php' => config_path('tenant.php'),
            ], 'tenant-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'tenant-migrations');

            $this->publishes([
                __DIR__.'/../routes/tenant.php' => base_path('routes/tenant-info.php'),
                __DIR__.'/../routes/tenant-admin.php' => base_path('routes/tenant-admin.php'),
            ], 'tenant-routes');
        }

        if (config('tenant.admin.enable', false)) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'tenant');
        }
    }

    /**
     * Register middleware alias.
     */
    protected function registerMiddlewareAlias(): void
    {
        try {
            /** @var Router $router */
            $router = $this->app->make('router');
            $router->aliasMiddleware('tenant', TenantMiddleware::class);
            $router->aliasMiddleware('tenant.is-internal', Http\Middleware\RequireInternalTenant::class);
        } catch (BindingResolutionException $e) {
            throw new RuntimeException('Failed to alias tenant middleware', 0 , $e);
        }
    }

    /**
     * Register tenant middleware automatically.
     */
    protected function registerTenantMiddleware(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        if (!config('tenant.middleware.auto_register', true)) {
            return;
        }

        try {
            /** @var Kernel $kernel */
            $kernel = $this->app->make(Kernel::class);
            $kernel->prependMiddleware(TenantMiddleware::class);
        } catch (BindingResolutionException $e) {
            throw new RuntimeException('Failed to register tenant middleware', 0, $e);
        }
    }

    /**
     * Register tenant config routes.
     */
    protected function registerRoutes(): void
    {
        if (config('tenant.api.enabled', true)) {
            Route::prefix(config('tenant.api.prefix', 'tenant-info'))
                ->name(config('tenant.api.name', 'tenant.'))
                ->middleware(config('tenant.api.middleware', []))
                ->group(__DIR__.'/../routes/tenant.php');
        }

        if (config('tenant.admin.enable', false)) {
            Route::prefix(config('tenant.admin.prefix', 'tenant-admin'))
                ->name(config('tenant.admin.name', 'tenant.admin.'))
                ->middleware(config('tenant.admin.middleware', ['tenant.is-internal']))
                ->group(__DIR__.'/../routes/tenant-admin.php');
        }
    }

    /**
     * Boot model scoping for config models.
     */
    protected function bootExternalModelScoping(): void
    {
        $models = config('tenant.scoped_models', []);
        $provider = $this; // Capture instance for use in closures

        foreach ($models as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $modelClass::addGlobalScope(new Scopes\TenantScope());

            $modelClass::creating(static function ($model) use ($provider) {
                if (!isset($model->tenant_id) && !tenant_bypassed()) {
                    $tenant = TenantContextFacade::current();

                    if ($tenant && $provider->tenantUsesIsolatedDatabase($tenant)) {
                        return;
                    }

                    $model->tenant_id = tenant_id();
                }
            });

            $modelClass::updating(static function ($model) use ($provider) {
                $provider->validateTenantMatch($model);
            });

            $modelClass::deleting(static function ($model) use ($provider) {
                $provider->validateTenantMatch($model);
            });
        }
    }

    /**
     * Register tenant context preservation hooks.
     */
    protected function registerContextPreservation(): void
    {
        if (!config('tenant.preserve_context', true)) {
            return;
        }

        Context::dehydrating(static function ($context): void {
            $tenant = TenantContextFacade::current();

            if ($tenant) {
                $context->addHidden('tenant', $tenant);
            }
        });

        Context::hydrated(function ($context): void {
            if ($context->hasHidden('tenant')) {
                $tenant = $context->getHidden('tenant');

                TenantContextFacade::setCurrent($tenant);

                app(ConfigurationPipeManager::class)->apply(
                    $tenant,
                    $this->app->make(ConfigRepository::class)
                );
            }
        });
    }

    /**
     * Register tenant-aware queue connector if enabled.
     */
    protected function registerTenantQueueConnector(): void
    {
        if (config('tenant.queue.auto_tenant_id', true)) {
            $this->app->resolving('queue', function (QueueManager $manager) {
                $manager->addConnector('database', function () {
                    return new TenantDatabaseConnector($this->app['db']);
                });
            });
        }
    }

    /**
     * Register a tenant-aware failed job provider if enabled.
     */
    protected function registerTenantFailedJobProvider(): void
    {
        if (config('tenant.queue.auto_tenant_id', true)) {
            $this->app->extend('queue.failer', function ($provider, $app) {
                if ($app['config']->get('queue.failed.database') !== null) {
                    return new TenantDatabaseUuidFailedJobProvider(
                        $app['db'],
                        $app['config']['queue.failed.database'],
                        $app['config']['queue.failed.table']
                    );
                }

                return $provider;
            });
        }
    }

    /**
     * Register a tenant-aware batch repository if enabled.
     */
    protected function registerTenantBatchRepository(): void
    {
        if (config('tenant.queue.auto_tenant_id', true)) {
            $this->app->extend(DatabaseBatchRepository::class, function ($repository, $app) {
                return new TenantDatabaseBatchRepository(
                    $app->make(BatchFactory::class),
                    $app->make('db')->connection($app->config->get('queue.batching.database')),
                    $app->config->get('queue.batching.table', 'job_batches')
                );
            });
        }
    }

    /**
     * Register tenant-aware session handler if enabled.
     */
    protected function registerTenantSessionHandler(): void
    {
        if (config('tenant.sessions.auto_tenant_id', false)) {
            $this->app->extend('session', function (SessionManager $manager, $app) {
                $manager->extend('database', function () use ($app) {
                    $table = $app['config']['session.table'];
                    $lifetime = $app['config']['session.lifetime'];
                    $connection = $app['db']->connection($app['config']['session.connection']);

                    return new TenantDatabaseSessionHandler($connection, $table, $lifetime, $app);
                });

                $manager->extend('file', function () use ($app) {
                    $path = $app['config']['session.files'];
                    $lifetime = $app['config']['session.lifetime'];

                    return new Session\TenantFileSessionHandler(
                        $path,
                        $lifetime,
                        $app->make(TenantContext::class)
                    );
                });

                $manager->extend('redis', function () use ($app) {
                    $cache = $app['cache']->store($app['config']['session.store']);
                    $lifetime = $app['config']['session.lifetime'];

                    return new Session\TenantRedisSessionHandler(
                        $cache,
                        $lifetime,
                        $app->make(TenantContext::class)
                    );
                });

                $manager->extend('memcached', function () use ($app) {
                    $cache = $app['cache']->store($app['config']['session.store']);
                    $lifetime = $app['config']['session.lifetime'];

                    return new Session\TenantMemcachedSessionHandler(
                        $cache,
                        $lifetime,
                        $app->make(TenantContext::class)
                    );
                });

                return $manager;
            });
        }
    }

    /**
     * Register a tenant-aware cache store if enabled.
     */
    protected function registerTenantCacheStore(): void
    {
        if (config('tenant.cache.auto_tenant_id', false)) {
            $this->app->extend('cache', function (CacheManager $manager) {
                $manager->extend('database', function ($app, $config) use ($manager) {
                    $connection = $app['db']->connection($config['connection'] ?? null);
                    $table = $config['table'];
                    $prefix = $config['prefix'] ?? '';
                    $lockTable = $config['lock_table'] ?? 'cache_locks';

                    $store = new TenantDatabaseStore(
                        $connection, $table, $prefix, $lockTable
                    );

                    return $manager->repository($store);
                });

                return $manager;
            });
        }
    }

    /**
     * Register a tenant-aware cache manager for automatic key scoping across all drivers.
     */
    protected function registerTenantCacheManager(): void
    {
        if (config('tenant.cache.auto_tenant_scoping', false)) {
            $this->app->extend('cache', function ($manager, $app) {
                return new Cache\TenantCacheManager(
                    $app,
                    $app->make(TenantContext::class)
                );
            });
        }
    }

    /**
     * Register tenant-aware password reset token repository if enabled.
     */
    protected function registerTenantPasswordResetTokenRepository(): void
    {
        if (config('tenant.password_reset_tokens.auto_tenant_id', false)) {
            $this->app->extend('auth.password', function ($manager, $app) {
                return new TenantPasswordBrokerManager($app);
            });
        }
    }

    /**
     * Register a tenant-aware broadcasting manager if enabled.
     */
    protected function registerTenantBroadcastingManager(): void
    {
        if (config('tenant.broadcasting.auto_tenant_id', true)) {
            $manager = $this->app->make(BroadcastManager::class);

            $manager->extend('log', function ($app) {
                return new Broadcasting\TenantLogBroadcaster(
                    $app->make('log'),
                    $app->make(TenantContext::class)
                );
            });

            if (class_exists(\Pusher\Pusher::class)) {
                $manager->extend('pusher', function ($app, $config) {
                    return new Broadcasting\TenantPusherBroadcaster(
                        new \Pusher\Pusher(
                            $config['key'],
                            $config['secret'],
                            $config['app_id'],
                            $config['options'] ?? []
                        ),
                        $app->make(TenantContext::class)
                    );
                });
            }

            if (class_exists(\Illuminate\Broadcasting\Broadcasters\ReverseProxyBroadcaster::class)) {
                $manager->extend('reverb', function ($app, $config) {
                    return new Broadcasting\TenantReverbBroadcaster(
                        $config['options'] ?? [],
                        $app->make(TenantContext::class)
                    );
                });
            }
        }
    }

    /**
     * Register tenant-aware filesystem manager if enabled.
     */
    protected function registerTenantFilesystemManager(): void
    {
        if (config('tenant.filesystems.auto_tenant_scoping', false)) {
            $this->app->extend('filesystem', function ($manager, $app) {
                return new Filesystem\TenantFilesystemManager(
                    $app,
                    $app->make(TenantContext::class)
                );
            });
        }
    }

    /**
     * Register tenant-aware mail manager if enabled.
     */
    protected function registerTenantMailManager(): void
    {
        if (config('tenant.mail.auto_tenant_mail', false)) {
            $this->app->extend('mail.manager', function ($manager, $app) {
                return new Mail\TenantMailManager(
                    $app,
                    $app->make(TenantContext::class)
                );
            });
        }
    }

    /**
     * Register tenant facades.
     */
    protected function registerFacades(): void
    {
        $this->app->alias(TenantContext::class, 'tenant.context');
    }
}