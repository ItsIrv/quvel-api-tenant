<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions\Admin;

use Quvel\Tenant\Builders\TenantConfigurationBuilder;
use Quvel\Tenant\Models\Tenant;

class UpdateTenant
{
    /**
     * Update an existing tenant.
     */
    public function __invoke(Tenant $tenant, array $data): Tenant
    {
        if (isset($data['name'])) {
            $tenant->name = $data['name'];
        }

        if (isset($data['identifier'])) {
            $tenant->identifier = $data['identifier'];
        }

        if (isset($data['config'])) {
            $configBuilder = new TenantConfigurationBuilder();

            foreach ($data['config'] as $key => $value) {
                if (!empty($value)) {
                    $configBuilder->setConfig($key, $value);
                }
            }

            $tenant->config = $configBuilder->toArray();
        }

        $tenant->save();

        return $tenant;
    }
}
