<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Events\TenantMiddlewareCompleted;
use Quvel\Tenant\Managers\ConfigurationPipeManager;
use Quvel\Tenant\Managers\TenantResolverManager;
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
        protected ConfigurationPipeManager $configPipeline
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantManager->shouldBypass($request)) {
            $this->tenantContext->bypass();

            return $next($request);
        }

        $tenant = $this->tenantManager->resolveTenant($request);

        if ($tenant === null) {
            $this->handleTenantNotFound($request);
        }

        $this->tenantContext->setCurrent($tenant);
        $this->configPipeline->apply($tenant, config());

        TenantMiddlewareCompleted::dispatch($tenant, $request);

        return $next($request);
    }

    /**
     * Handle when no tenant is found.
     */
    protected function handleTenantNotFound(Request $request): never
    {
        $strategy = config('tenant.not_found.strategy');
        $config = config('tenant.not_found.config', []);

        match ($strategy) {
            'redirect' => redirect($config['redirect_url'] ?? '/')->send(),
            'custom' => $this->callCustomHandler($config['handler'] ?? null, $request),
            default => throw new NotFoundHttpException('Tenant not found'),
        };

        exit;
    }

    /**
     * Call a custom tenant not found handler.
     */
    protected function callCustomHandler(mixed $handler, Request $request): never
    {
        if ($handler === null) {
            throw new NotFoundHttpException('Tenant not found');
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = app($handler);
        }

        if (is_callable($handler)) {
            $handler($request);
            exit;
        }

        throw new NotFoundHttpException('Tenant not found');
    }
}