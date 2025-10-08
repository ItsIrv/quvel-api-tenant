<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

use Illuminate\Http\Request;

/**
 * Contract for tenant resolution service.
 */
interface ResolutionService
{
    /**
     * Resolve tenant from request.
     *
     * @param Request $request The HTTP request
     * @return mixed The resolved tenant or null if none found
     */
    public function resolve(Request $request): mixed;

    /**
     * Set a callback to determine if a tenant resolution should be bypassed.
     *
     * @param callable|null $callback Receives Request, returns bool
     */
    public function setBypassCallback(?callable $callback): void;

    /**
     * Check if the tenant resolution should be bypassed for this request.
     */
    public function shouldBypass(Request $request): bool;
}
