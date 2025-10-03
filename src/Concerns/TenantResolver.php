<?php

declare(strict_types=1);

namespace Quvel\Tenant\Concerns;

use Illuminate\Http\Request;
use Quvel\Tenant\Models\Tenant;

/**
 * Contract for tenant resolution strategies.
 */
interface TenantResolver
{
    /**
     * Resolve the current tenant from the request.
     *
     * @param Request $request The HTTP request
     * @return Tenant|null The resolved tenant or null if none found
     */
    public function resolve(Request $request): ?Tenant;
}