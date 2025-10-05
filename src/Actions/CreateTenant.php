<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions;

use Illuminate\Support\Str;
use Quvel\Tenant\Builders\TenantConfigurationBuilder;

/**
 * Action for creating tenants with configuration.
 */
class CreateTenant
{
    public function execute(
        string $name,
        string $identifier,
        TenantConfigurationBuilder $configBuilder,
        ?int $parentId = null,
        bool $isActive = true,
        bool $isInternal = false
    ) {
        $tenant = tenant_model();
        $tenant->public_id = Str::ulid()->toString();
        $tenant->name = $name;
        $tenant->identifier = $identifier;
        $tenant->parent_id = $parentId;
        $tenant->is_active = $isActive;
        $tenant->is_internal = $isInternal;
        $tenant->config = $configBuilder->toArray();
        $tenant->save();

        return $tenant;
    }
}