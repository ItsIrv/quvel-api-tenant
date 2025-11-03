<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Quvel\Tenant\Contracts\TenantContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Middleware to ensure the current tenant is marked as internal.
 * Used for endpoints that should only be accessible by internal tenants.
 */
class RequireInternalTenant
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->current();

        if (!$tenant || !$tenant->isInternal()) {
            throw new NotFoundHttpException('Endpoint not available');
        }

        return $next($request);
    }
}
