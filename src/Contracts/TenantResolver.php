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
     * Resolve the current tenant from the request.
     *
     * @param Request $request The HTTP request
     * @return mixed The resolved tenant or null if none found
     */
    public function resolve(Request $request);
}