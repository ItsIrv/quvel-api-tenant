<?php

declare(strict_types=1);

namespace Quvel\Tenant;

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
use Quvel\Tenant\Managers\TenantTableManager;
use Quvel\Tenant\Http\Middleware\TenantMiddleware;
use Quvel\Tenant\Managers\ConfigurationPipeManager;
use Quvel\Tenant\Managers\TenantResolverManager;
use Quvel\Tenant\Queue\Connectors\TenantDatabaseConnector;
use Quvel\Tenant\Queue\Failed\TenantDatabaseUuidFailedJobProvider;
use Quvel\Tenant\Queue\TenantDatabaseBatchRepository;
use Quvel\Tenant\Session\TenantDatabaseSessionHandler;
use Quvel\Tenant\Traits\HandlesTenantModels;
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

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tenant.php' => config_path('tenant.php'),
            ], 'tenant-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'tenant-migrations');
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
        Route::prefix('tenant-info')
            ->name('tenant.')
            ->group(__DIR__.'/../routes/tenant.php');
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
                    $tenant = app(TenantContext::class)->current();

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
            $tenant = app(TenantContext::class)->current();

            if ($tenant) {
                $context->addHidden('tenant', $tenant);
            }
        });

        Context::hydrated(function ($context): void {
            if ($context->hasHidden('tenant')) {
                $tenant = $context->getHidden('tenant');

                app(TenantContext::class)->setCurrent($tenant);

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
     * Register tenant-aware failed job provider if enabled.
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
     * Register tenant-aware batch repository if enabled.
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

                return $manager;
            });
        }
    }

    /**
     * Register tenant-aware cache store if enabled.
     */
    protected function registerTenantCacheStore(): void
    {
        if (config('tenant.cache.auto_tenant_id', false)) {
            $this->app->extend('cache', function (CacheManager $manager, $app) {
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
}