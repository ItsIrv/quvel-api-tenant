<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Managers\TenantResolverManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Middleware - Resolves and applies tenant context.
 */
class TenantMiddleware
{
    public function __construct(
        protected TenantResolverManager $tenantManager,
        protected TenantContext $tenantContext
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantManager->resolveTenant($request);

        $this->tenantContext->setCurrent($tenant);

        return $next($request);
    }
}