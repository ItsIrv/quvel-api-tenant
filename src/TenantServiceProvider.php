<?php

declare(strict_types=1);

namespace Quvel\Tenant;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Database\TenantTableRegistry;
use Quvel\Tenant\Http\Middleware\TenantMiddleware;
use Quvel\Tenant\Managers\ConfigurationPipeManager;
use Quvel\Tenant\Managers\TenantResolverManager;
use RuntimeException;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/tenant.php',
            'tenant'
        );

        $this->app->singleton(TenantTableRegistry::class, function () {
            return new TenantTableRegistry();
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
            $router->aliasMiddleware('tenant.internal', Http\Middleware\RequireInternalTenant::class);
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
     * Boot external model scoping for Laravel/package models.
     *
     * The scoped_models configuration provides tenant scoping (WHERE tenant_id = ?)
     * and tenant-aware model events. Database connection switching is handled
     * automatically by the DatabaseConfigPipe when tenants have database overrides.
     *
     * Models in scoped_models automatically get:
     * - Tenant scoping (WHERE tenant_id filtering)
     * - Tenant-aware model events (creating/updating/deleting guards)
     * - Automatic database connection switching (via global default connection)
     * - Isolated database support (skips tenant_id logic when appropriate)
     *
     * No manual traits required - third-party packages work automatically.
     */
    protected function bootExternalModelScoping(): void
    {
        $models = config('tenant.scoped_models', []);

        foreach ($models as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $this->addTenantScopingToModel($modelClass);
            $this->addTenantEventsToModel($modelClass);
        }
    }

    /**
     * Add tenant scoping to external model.
     */
    protected function addTenantScopingToModel(string $modelClass): void
    {
        /** @var $modelClass Model */
        $modelClass::addGlobalScope(new Scopes\TenantScope());
    }


    /**
     * Add tenant-aware model events to external model.
     */
    protected function addTenantEventsToModel(string $modelClass): void
    {
        /** @var $modelClass Model */
        $modelClass::creating(static function ($model) {
            if (!isset($model->tenant_id) && !tenant_bypassed()) {
                $tenant = app(TenantContext::class)->current();

                // Skip tenant_id assignment for isolated databases
                if ($tenant && self::tenantUsesIsolatedDatabase($tenant)) {
                    return;
                }

                $model->tenant_id = tenant_id();
            }
        });

        $modelClass::updating(static function ($model) {
            if (tenant_bypassed()) {
                return;
            }

            $tenant = app(TenantContext::class)->current();

            // Skip tenant_id checks for isolated databases
            if ($tenant && self::tenantUsesIsolatedDatabase($tenant)) {
                return;
            }

            $currentTenantId = tenant_id();
            if ($model->tenant_id !== $currentTenantId) {
                throw new Exceptions\TenantMismatchException(
                    sprintf(
                        'Cannot update %s with tenant_id %s from tenant %s',
                        get_class($model),
                        $model->tenant_id,
                        $currentTenantId
                    )
                );
            }
        });

        $modelClass::deleting(static function ($model) {
            if (tenant_bypassed()) {
                return;
            }

            $tenant = app(TenantContext::class)->current();

            // Skip tenant_id checks for isolated databases
            if ($tenant && self::tenantUsesIsolatedDatabase($tenant)) {
                return;
            }

            $currentTenantId = tenant_id();
            if ($model->tenant_id !== $currentTenantId) {
                throw new Exceptions\TenantMismatchException(
                    sprintf(
                        'Cannot delete %s with tenant_id %s from tenant %s',
                        get_class($model),
                        $model->tenant_id,
                        $currentTenantId
                    )
                );
            }
        });
    }

    /**
     * Check if tenant uses isolated database (separate database/server).
     */
    protected static function tenantUsesIsolatedDatabase($tenant): bool
    {
        $baseConnection = $tenant->getConfig('database.default') ?? 'mysql';

        // Isolated if tenant has custom host OR custom database name
        return $tenant->hasConfig("database.connections.$baseConnection.host") ||
               $tenant->hasConfig("database.connections.$baseConnection.database");
    }
}