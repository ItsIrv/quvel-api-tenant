<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Quvel\Tenant\Contracts\TenantContext;

class TenantAwareStartSession extends StartSession
{
    private readonly TenantContext $tenantContext;

    public function __construct(
        SessionManager $manager,
        ?callable $cacheFactoryResolver = null
    ) {
        parent::__construct($manager, $cacheFactoryResolver);

        $this->tenantContext = app(TenantContext::class);
    }

    /**
     * Handle the given request within the session state.
     */
    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        $request->setLaravelSession(
            $this->startSession($request, $session)
        );

        $this->collectGarbage($session);

        $this->validateTenantSession($request, $session);

        $response = $next($request);

        $this->storeCurrentUrl($request, $session);

        $this->addCookieToResponse($response, $session);

        $this->saveSession($request);

        return $response;
    }

    /**
     * Start the session for the given request.
     */
    protected function startSession(Request $request, $session)
    {
        $session = parent::startSession($request, $session);

        if (!$this->tenantContext->isBypassed()) {
            $currentTenant = $this->tenantContext->current();

            if ($currentTenant) {
                $session->put('tenant_id', $currentTenant->id);
            }
        }

        return $session;
    }

    /**
     * Validate that the session belongs to the current tenant.
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
     */
    protected function validateTenantSession(Request $request, Session $session): void
    {
        if ($this->tenantContext->isBypassed()) {
            return;
        }

        $currentTenant = $this->tenantContext->current();

        if (!$currentTenant) {
            return;
        }

        if (!$session->has('tenant_id') || $session->get('tenant_id') !== $currentTenant->id) {
            if (Auth::check()) {
                Auth::logout();
            }

            $session->invalidate();
            $session->regenerateToken();
        }
    }
}
