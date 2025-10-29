<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Quvel\Tenant\Context\TenantContext;

/**
 * Middleware to validate that the session belongs to the current tenant.
 * This prevents cross-tenant session hijacking by ensuring the session's
 * tenant_id matches the current tenant context.
 */
class ValidateTenantSession
{
    /**
     * Create a new ValidateTenantSession instance.
     */
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->tenantContext->isBypassed()) {
            return $next($request);
        }

        $currentTenant = $this->tenantContext->current();

        if (!$currentTenant) {
            return $next($request);
        }

        $session = $request->session();

        if ($session->has('tenant_id')) {
            $sessionTenantId = $session->get('tenant_id');

            if ($sessionTenantId !== $currentTenant->id) {
                $session->invalidate();
                $session->regenerateToken();

                if (Auth::check()) {
                    Auth::logout();
                }

                $session->put('tenant_id', $currentTenant->id);
            }
        } else {
            $session->put('tenant_id', $currentTenant->id);
        }

        if (Auth::check()) {
            $user = Auth::user();

            if ($user === null) {
                return $next($request);
            }

            if (isset($user->tenant_id) && $user->tenant_id !== $currentTenant->id) {
                Auth::logout();

                $session->invalidate();
                $session->regenerateToken();
                $session->put('tenant_id', $currentTenant->id);
            }
        }

        return $next($request);
    }
}
