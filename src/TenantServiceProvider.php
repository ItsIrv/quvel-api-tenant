<?php

declare(strict_types=1);

namespace Quvel\Tenant;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Routing\Router;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;
use Quvel\Tenant\Auth\TenantPasswordBrokerManager;
use Quvel\Tenant\Cache\TenantDatabaseStore;
use Quvel\Tenant\Concerns\HandlesTenantModels;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Contracts\PipelineRegistry as PipelineRegistryContract;
use Quvel\Tenant\Contracts\ResolutionService as ResolutionServiceContract;
use Quvel\Tenant\Contracts\TableRegistry as TableRegistryContract;
use Quvel\Tenant\Contracts\TenantContext as TenantContextContract;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Database\TableRegistry;
use Quvel\Tenant\Facades\TenantContext as TenantContextFacade;
use Quvel\Tenant\Http\Middleware\TenantMiddleware;
use Quvel\Tenant\Pipes\PipelineRegistry;
use Quvel\Tenant\Queue\Failed\TenantDatabaseUuidFailedJobProvider;
use Quvel\Tenant\Queue\TenantDatabaseBatchRepository;
use Quvel\Tenant\Redis\TenantAwareRedisManager;
use Quvel\Tenant\Resolution\ResolutionService;
use Quvel\Tenant\Resolution\ResolverManager;
use Quvel\Tenant\Session\TenantDatabaseSessionHandler;
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
            __DIR__ . '/../config/tenant.php',
            'tenant'
        );

        $this->app->singleton(
            TableRegistryContract::class,
            TableRegistry::class
        );

        $this->app->singleton(ResolverManager::class);
        $this->app->singleton(TenantResolver::class, function ($app) {
            /** @var ResolverManager $manager */
            $manager = $app->make(ResolverManager::class);

            return $manager->driver($manager->getDefaultDriver());
        });

        $this->app->singleton(
            ResolutionServiceContract::class,
            ResolutionService::class
        );

        $this->app->singleton(
            PipelineRegistryContract::class,
            PipelineRegistry::class
        );

        $this->app->scoped(
            TenantContextContract::class,
            TenantContext::class
        );

        $this->registerAutoResolveTenantModel();

        $this->registerTenantQueueConnector();
        $this->registerTenantBatchRepository();
        $this->registerTenantDatabaseManager();
        $this->registerTenantSessionManager();
        $this->registerTenantSessionHandler();
        $this->registerTenantCacheStore();
        $this->registerTenantCacheManager();
        $this->registerTenantPasswordResetTokenRepository();
        $this->registerTenantFilesystemManager();
        $this->registerTenantMailManager();
        $this->registerTenantRedisManager();
        $this->registerFacades();

        if ($this->app->runningInConsole()) {
            $this->registerTenantCommands();
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerBlueprintMacros();
        $this->registerTenantMiddleware();
        $this->registerMiddlewareAlias();
        $this->registerRoutes();
        $this->bootExternalModelScoping();
        $this->registerContextPreservation();
        $this->registerTenantFailedJobProvider();
        $this->registerTenantBroadcastingManager();
        $this->registerTenantTelescopeRepository();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tenant.php' => config_path('tenant.php'),
            ], 'tenant-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/2024_01_01_000000_create_tenants_table.php' =>
                    database_path('migrations/' . date('Y_m_d_His') . '_create_tenants_table.php'),
                __DIR__ . '/../database/migrations/2024_01_02_000000_add_tenant_id_to_tables.php' =>
                    database_path('migrations/' . date('Y_m_d_His', time() + 1) . '_add_tenant_id_to_tables.php'),
            ], 'tenant-migrations');

            $this->publishes([
                __DIR__ . '/../routes/tenant-config.php' => base_path('routes/tenant-info.php'),
            ], 'tenant-config-routes');

            $this->publishes([
                __DIR__ . '/../routes/tenant-admin.php' => base_path('routes/tenant-admin.php'),
            ], 'tenant-admin-routes');
        }

        if (config('tenant.admin.enabled', false)) {
            $this->loadViewsFrom(__DIR__ . '/../resources/views', 'tenant');
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
            $router->aliasMiddleware('tenant.csrf', Http\Middleware\TenantAwareCsrfToken::class);
        } catch (BindingResolutionException $bindingResolutionException) {
            throw new RuntimeException('Failed to alias tenant middleware', 0, $bindingResolutionException);
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
        } catch (BindingResolutionException $bindingResolutionException) {
            throw new RuntimeException('Failed to register tenant middleware', 0, $bindingResolutionException);
        }
    }

    /**
     * Register tenant config routes.
     */
    protected function registerRoutes(): void
    {
        if (config('tenant.api.enabled', true)) {
            Route::prefix(config('tenant.api.prefix', 'tenant-info'))
                ->name(config('tenant.api.name', 'tenant.config.'))
                ->middleware(config('tenant.api.middleware', ['tenant.is-internal']))
                ->group(__DIR__ . '/../routes/tenant-config.php');
        }

        if (config('tenant.admin.enabled', false)) {
            Route::prefix(config('tenant.admin.prefix', 'tenant-admin'))
                ->name(config('tenant.admin.name', 'tenant.admin.'))
                ->middleware(config('tenant.admin.middleware', ['tenant.is-internal']))
                ->group(__DIR__ . '/../routes/tenant-admin.php');
        }
    }

    /**
     * Boot model scoping for config models.
     */
    protected function bootExternalModelScoping(): void
    {
        $models = config('tenant.scoped_models', []);

        if (
            class_exists(\Laravel\Telescope\Storage\EntryModel::class) && config(
                'tenant.telescope.tenant_scoped',
                false
            )
        ) {
            $models[] = \Laravel\Telescope\Storage\EntryModel::class;
        }

        $provider = $this; // Capture instance for use in closures

        foreach ($models as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $modelClass::addGlobalScope(new Scopes\TenantScope());

            $modelClass::creating(static function ($model): void {
                if (!isset($model->tenant_id) && !tenant_bypassed()) {
                    if (!TenantContextFacade::needsTenantIdScope()) {
                        return;
                    }

                    $model->tenant_id = tenant_id();
                }
            });

            $modelClass::updating(static function ($model) use ($provider): void {
                $provider->validateTenantMatch($model);
            });

            $modelClass::deleting(static function ($model) use ($provider): void {
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

        Context::hydrated(static function ($context): void {
            if ($context->hasHidden('tenant')) {
                $tenant = $context->getHidden('tenant');

                TenantContextFacade::setCurrent($tenant);

                app(PipelineRegistryContract::class)->applyPipes($tenant);
            }
        });
    }

    /**
     * Register tenant-aware queue connectors if enabled.
     */
    protected function registerTenantQueueConnector(): void
    {
        if (config('tenant.queue.auto_tenant_id', true)) {
            $this->app->afterResolving('queue', function (QueueManager $manager): void {
                $manager->addConnector(
                    'database',
                    fn(
                    ): \Quvel\Tenant\Queue\Connectors\TenantDatabaseConnector => new Queue\Connectors\TenantDatabaseConnector(
                        $this->app['db']
                    )
                );

                if (class_exists(Horizon::class)) {
                    $manager->addConnector(
                        'redis',
                        fn(
                        ): \Quvel\Tenant\Queue\Connectors\TenantHorizonConnector => new Queue\Connectors\TenantHorizonConnector(
                            $this->app['redis']
                        )
                    );
                } else {
                    $manager->addConnector(
                        'redis',
                        fn(
                        ): \Quvel\Tenant\Queue\Connectors\TenantRedisConnector => new Queue\Connectors\TenantRedisConnector(
                            $this->app['redis']
                        )
                    );
                }
            });
        }
    }

    /**
     * Register a tenant-aware failed job provider if enabled.
     */
    protected function registerTenantFailedJobProvider(): void
    {
        if (config('tenant.queue.auto_tenant_id', true)) {
            $this->app->extend('queue.failer', function ($provider, array $app) {
                if ($app['config']->get('queue.failed.database') !== null) {
                    return new TenantDatabaseUuidFailedJobProvider(
                        $app['db'],
                        $app['config']->get('queue.failed.database'),
                        $app['config']->get('queue.failed.table')
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
            $this->app->extend(
                DatabaseBatchRepository::class,
                fn(
                    $repository,
                    $app
                ): \Quvel\Tenant\Queue\TenantDatabaseBatchRepository => new TenantDatabaseBatchRepository(
                    $app->make(BatchFactory::class),
                    $app->make('db')->connection($app->config->get('queue.batching.database')),
                    $app->config->get('queue.batching.table', 'job_batches')
                )
            );
        }
    }

    /**
     * Register tenant-aware database manager.
     */
    protected function registerTenantDatabaseManager(): void
    {
        $this->app->extend(
            'db',
            fn($manager, $app): \Quvel\Tenant\Database\TenantDatabaseManager => new Database\TenantDatabaseManager(
                $app,
                $app['db.factory'],
                $app->make(TenantContext::class)
            )
        );
    }

    /**
     * Register tenant-aware session manager.
     */
    protected function registerTenantSessionManager(): void
    {
        $this->app->extend(
            'session',
            fn($manager, $app): \Quvel\Tenant\Session\TenantSessionManager => new Session\TenantSessionManager($app)
        );
    }

    /**
     * Register tenant-aware session handler if enabled.
     */
    protected function registerTenantSessionHandler(): void
    {
        if (config('tenant.sessions.auto_tenant_id', false)) {
            $this->app->extend('session', function (SessionManager $manager, $app): \Illuminate\Session\SessionManager {
                $manager->extend(
                    'database',
                    function () use ($app): \Quvel\Tenant\Session\TenantDatabaseSessionHandler {
                        $table = $app['config']->get('session.table');
                        $lifetime = $app['config']->get('session.lifetime');
                        $connection = $app['db']->connection($app['config']->get('session.connection'));

                        return new TenantDatabaseSessionHandler($connection, $table, $lifetime, $app);
                    }
                );

                $manager->extend('file', function () use ($app): \Quvel\Tenant\Session\TenantFileSessionHandler {
                    $path = $app['config']->get('session.files');
                    $lifetime = $app['config']->get('session.lifetime');

                    return new Session\TenantFileSessionHandler(
                        $path,
                        $lifetime,
                        $app->make(TenantContext::class)
                    );
                });

                $manager->extend('redis', function () use ($app): \Quvel\Tenant\Session\TenantRedisSessionHandler {
                    $cache = $app['cache']->store($app['config']->get('session.store'));
                    $lifetime = $app['config']->get('session.lifetime');

                    return new Session\TenantRedisSessionHandler(
                        $cache,
                        $lifetime,
                        $app->make(TenantContext::class)
                    );
                });

                $manager->extend(
                    'memcached',
                    function () use ($app): \Quvel\Tenant\Session\TenantMemcachedSessionHandler {
                        $cache = $app['cache']->store($app['config']->get('session.store'));
                        $lifetime = $app['config']->get('session.lifetime');

                        return new Session\TenantMemcachedSessionHandler(
                            $cache,
                            $lifetime,
                            $app->make(TenantContext::class)
                        );
                    }
                );

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
            $this->app->extend('cache', function (CacheManager $manager): \Illuminate\Cache\CacheManager {
                $manager->extend('database', function (Application $app, array $config) use ($manager) {
                    $connection = $app['db']->connection($config['connection'] ?? null);
                    $table = $config['table'];
                    $prefix = $config['prefix'] ?? '';
                    $lockTable = $config['lock_table'] ?? 'cache_locks';

                    $store = new TenantDatabaseStore(
                        $connection,
                        $table,
                        $prefix,
                        $lockTable
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
            $this->app->extend(
                'cache',
                fn($manager, $app): \Quvel\Tenant\Cache\TenantCacheManager => new Cache\TenantCacheManager(
                    $app,
                    $app->make(TenantContext::class)
                )
            );
        }
    }

    /**
     * Register tenant-aware password reset token repository if enabled.
     */
    protected function registerTenantPasswordResetTokenRepository(): void
    {
        if (config('tenant.password_reset_tokens.auto_tenant_id', false)) {
            $this->app->extend(
                'auth.password',
                fn($manager, $app): \Quvel\Tenant\Auth\TenantPasswordBrokerManager => new TenantPasswordBrokerManager(
                    $app
                )
            );
        }
    }

    /**
     * Register a tenant-aware broadcasting manager if enabled.
     */
    protected function registerTenantBroadcastingManager(): void
    {
        if (config('tenant.broadcasting.auto_tenant_id', true)) {
            $manager = $this->app->make(BroadcastManager::class);

            $manager->extend(
                'log',
                fn($app): \Quvel\Tenant\Broadcasting\TenantLogBroadcaster => new Broadcasting\TenantLogBroadcaster(
                    $app->make('log'),
                    $app->make(TenantContext::class)
                )
            );

            if (class_exists(\Pusher\Pusher::class)) {
                $manager->extend(
                    'pusher',
                    fn(
                        $app,
                        $config
                    ): \Quvel\Tenant\Broadcasting\TenantPusherBroadcaster => new Broadcasting\TenantPusherBroadcaster(
                        new \Pusher\Pusher(
                            $config['key'],
                            $config['secret'],
                            $config['app_id'],
                            $config['options'] ?? []
                        ),
                        $app->make(TenantContext::class)
                    )
                );
            }

            if (class_exists(\Illuminate\Broadcasting\Broadcasters\ReverseProxyBroadcaster::class)) {
                $manager->extend(
                    'reverb',
                    fn(
                        $app,
                        $config
                    ): \Quvel\Tenant\Broadcasting\TenantReverbBroadcaster => new Broadcasting\TenantReverbBroadcaster(
                        $config['options'] ?? [],
                        $app->make(TenantContext::class)
                    )
                );
            }
        }
    }

    /**
     * Register tenant-aware filesystem manager if enabled.
     */
    protected function registerTenantFilesystemManager(): void
    {
        if (config('tenant.filesystems.auto_tenant_scoping', false)) {
            $this->app->extend(
                'filesystem',
                fn(
                    $manager,
                    $app
                ): \Quvel\Tenant\Filesystem\TenantFilesystemManager => new Filesystem\TenantFilesystemManager(
                    $app,
                    $app->make(TenantContext::class)
                )
            );
        }
    }

    /**
     * Register tenant-aware mail manager if enabled.
     */
    protected function registerTenantMailManager(): void
    {
        if (config('tenant.mail.auto_tenant_mail', false)) {
            $this->app->extend(
                'mail.manager',
                fn($manager, $app): \Quvel\Tenant\Mail\TenantMailManager => new Mail\TenantMailManager(
                    $app,
                    $app->make(TenantContext::class)
                )
            );
        }
    }

    /**
     * Register tenant-aware Redis manager for Horizon.
     *
     * Wraps Laravel's Redis manager to automatically prefix all Redis keys
     * with the current tenant ID, enabling tenant isolation for Horizon.
     */
    protected function registerTenantRedisManager(): void
    {
        if (!class_exists(Horizon::class)) {
            return;
        }

        if (!config('tenant.horizon.tenant_scoped', false)) {
            return;
        }

        $this->app->extend(
            'redis',
            fn($redis, $app): \Quvel\Tenant\Redis\TenantAwareRedisManager => new TenantAwareRedisManager(
                $app,
                config('database.redis.client', 'phpredis'),
                config('database.redis')
            )
        );
    }

    /**
     * Register a tenant-aware Telescope repository if Telescope is installed.
     */
    protected function registerTenantTelescopeRepository(): void
    {
        if (!interface_exists(\Laravel\Telescope\Contracts\EntriesRepository::class)) {
            return;
        }

        if (!config('tenant.telescope.tenant_scoped', false)) {
            return;
        }

        $this->app->singleton(
            \Laravel\Telescope\Contracts\EntriesRepository::class,
            fn(
            ): \Quvel\Tenant\Telescope\TenantAwareDatabaseEntriesRepository => new \Quvel\Tenant\Telescope\TenantAwareDatabaseEntriesRepository(
                config('telescope.storage.database.connection'),
                config('telescope.storage.database.chunk', 1000)
            )
        );
    }

    /**
     * Register automatic tenant model resolution if enabled.
     *
     * When enabled, type-hinting the tenant model will automatically resolve
     * to the current tenant from TenantContext instead of creating a new instance.
     */
    protected function registerAutoResolveTenantModel(): void
    {
        if (!config('tenant.auto_resolve_model', false)) {
            return;
        }

        $this->app->bind(tenant_class(), function ($app) {
            /** @var TenantContextContract $context */
            $context = $app->make(TenantContextContract::class);
            $tenant = $context->current();

            if ($tenant === null) {
                throw new RuntimeException(
                    'Cannot auto-resolve tenant model: No tenant is set in the current context. ' .
                    'Ensure tenant middleware has run or manually set a tenant via TenantContext::setCurrent().'
                );
            }

            return $tenant;
        });
    }

    /**
     * Register tenant facades.
     */
    protected function registerFacades(): void
    {
        $this->app->alias(TenantContext::class, 'tenant.context');
    }

    /**
     * Replaces Laravel's built-in commands with tenant-aware versions.
     */
    protected function registerTenantCommands(): void
    {
        $this->app->afterResolving('command.tinker', function ($command, $app): void {
            $app->forgetInstance('command.tinker');
            $app->singleton(
                'command.tinker',
                fn($app): \Quvel\Tenant\Console\TenantTinkerCommand => new Console\TenantTinkerCommand()
            );
        });

        $this->app->singleton(
            'command.tinker',
            fn($app): \Quvel\Tenant\Console\TenantTinkerCommand => new Console\TenantTinkerCommand()
        );

        $this->commands(['command.tinker']);

        $this->app->extend(
            WorkCommand::class,
            fn($command, $app): \Quvel\Tenant\Console\TenantQueueWorkCommand => new Console\TenantQueueWorkCommand(
                $app['queue.worker'],
                $app['cache.store']
            )
        );
    }

    /**
     * Register Blueprint macros for tenant columns.
     */
    protected function registerBlueprintMacros(): void
    {
        /**
         * Add a tenant_id column to the table with fine-grained control.
         *
         * @param string $after Column to place tenant_id after (default: 'id')
         * @param bool $cascadeDelete Whether to cascade on tenant deletion (default: true)
         * @param array $dropUniques Unique constraints to drop
         * @param array $tenantUniqueConstraints Unique constraints that should include tenant_id
         * @return void
         *
         * @example
         * Schema::create('posts', function (Blueprint $table) {
         *     $table->id();
         *     $table->tenantId(); // Simple case
         * });
         *
         * @example
         * Schema::create('posts', function (Blueprint $table) {
         *     $table->id();
         *     $table->string('slug');
         *     $table->tenantId(
         *         after: 'id',
         *         cascadeDelete: true,
         *         dropUniques: [['slug']],
         *         tenantUniqueConstraints: [['slug']]
         *     );
         * });
         */
        Blueprint::macro('tenantId', function (
            string $after = 'id',
            bool $cascadeDelete = true,
            array $dropUniques = [],
            array $tenantUniqueConstraints = []
        ): void {
            /** @var Blueprint $this */
            TableRegistry::addTenantColumn(
                $this,
                $after,
                $cascadeDelete,
                $dropUniques,
                $tenantUniqueConstraints
            );
        });
    }
}
