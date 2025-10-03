<?php

use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Models\Tenant;

if (!function_exists('tenant')) {
    /**
     * Get the current tenant or tenant context.
     *
     * @return Tenant|null
     */
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->current();
    }
}