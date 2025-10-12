<?php

declare(strict_types=1);

namespace Quvel\Tenant\Admin\Actions;

use Quvel\Tenant\Models\Tenant;

class ListTenants
{
    /**
     * List all tenants.
     */
    public function __invoke(): array
    {
        return Tenant::select(['id', 'public_id', 'name', 'identifier', 'config', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
