<?php

declare(strict_types=1);

namespace Quvel\Tenant;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Managers\TenantTableManager;
use Quvel\Tenant\Http\Middleware\TenantMiddleware;
use Quvel\Tenant\Managers\ConfigurationPipeManager;
use Quvel\Tenant\Managers\TenantResolverManager;
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

}