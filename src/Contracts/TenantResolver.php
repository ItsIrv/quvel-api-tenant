<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

use Illuminate\Http\Request;

/**
 * Contract for tenant resolution strategies.
 */
interface TenantResolver
{
    /**
     * Extract the tenant identifier from the request.
     *
     * @param Request $request The HTTP request
     * @return string|null The tenant identifier or null if none found
     */
    public function getIdentifier(Request $request): ?string;

    /**
     * Resolve the current tenant from the request.
     *
     * @param Request $request The HTTP request
     * @return mixed The resolved tenant or null if none found
     */
    public function resolve(Request $request): mixed;
}
