<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions\Admin;

use Quvel\Tenant\Models\Tenant;

class UpdateTenant
{
    /**
     * Update an existing tenant.
     */
    public function execute(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh();
    }
}
