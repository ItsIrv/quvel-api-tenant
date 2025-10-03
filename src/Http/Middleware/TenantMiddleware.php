<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Managers\TenantResolverManager;
use Quvel\Tenant\Models\Tenant;
use Quvel\Tenant\Services\ConfigurationPipeline;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tenant Middleware - Resolves and applies tenant context.
 */
class TenantMiddleware
{
    public function __construct(
        protected TenantResolverManager $tenantManager,
        protected TenantContext $tenantContext,
        protected ConfigurationPipeline $configPipeline
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantManager->resolveTenant($request);

        if ($tenant === null) {
            $tenant = $this->handleTenantNotFound($request);
        }

        $this->tenantContext->setCurrent($tenant);

        if ($tenant !== null) {
            $this->configPipeline->apply($tenant, config());
        }

        return $next($request);
    }

    /**
     * Handle when no tenant is found.
     */
    protected function handleTenantNotFound(Request $request): ?Tenant
    {
        $strategy = config('tenant.not_found.strategy', 'abort');
        $config = config('tenant.not_found.config', []);

        return match ($strategy) {
            'allow_null' => null,
            'abort' => throw new NotFoundHttpException('Tenant not found'),
            'redirect' => redirect($config['redirect_url'] ?? '/'),
            'default_tenant' => $this->getDefaultTenant($config['default_identifier'] ?? null),
            'custom' => $this->callCustomHandler($config['handler'] ?? null, $request),
            default => null,
        };
    }

    /**
     * Get the default tenant by identifier.
     */
    protected function getDefaultTenant(?string $identifier): ?Tenant
    {
        if ($identifier === null) {
            return null;
        }

        return Tenant::findByIdentifier($identifier);
    }

    /**
     * Call a custom tenant not found handler.
     */
    protected function callCustomHandler(mixed $handler, Request $request): ?Tenant
    {
        if ($handler === null) {
            return null;
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = app($handler);
        }

        if (is_callable($handler)) {
            return $handler($request);
        }

        return null;
    }
}