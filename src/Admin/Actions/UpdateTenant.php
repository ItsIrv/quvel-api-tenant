<?php

declare(strict_types=1);

namespace Quvel\Tenant\Admin\Actions;

use Quvel\Tenant\Builders\TenantConfigurationBuilder;
use Quvel\Tenant\Models\Tenant;

class UpdateTenant
{
    use NormalizesVisibility;

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
            if (isset($data['config']['__visibility']) && is_array($data['config']['__visibility'])) {
                $data['config']['__visibility'] = $this->normalizeVisibility($data['config']['__visibility']);
            }

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
