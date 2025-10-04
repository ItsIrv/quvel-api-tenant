<?php

declare(strict_types=1);

namespace Quvel\Tenant;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Quvel\Tenant\Concerns\TenantResolver;
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

        $this->app->bind(TenantContext::class, function () {
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
}