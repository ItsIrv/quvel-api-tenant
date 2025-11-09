<?php

declare(strict_types=1);

namespace Quvel\Tenant\Admin\Actions;

use Quvel\Tenant\Actions\CreateTenant;
use Quvel\Tenant\Builders\TenantConfigurationBuilder;
use Quvel\Tenant\Models\Tenant;

class StoreTenant
{
    use NormalizesVisibility;

    /**
     * Create a new tenant with configuration.
     */
    public function __invoke(string $name, string $identifier, array $config = []): Tenant
    {
        if (isset($config['__visibility']) && is_array($config['__visibility'])) {
            $config['__visibility'] = $this->normalizeVisibility($config['__visibility']);
        }

        $configBuilder = new TenantConfigurationBuilder();

        foreach ($config as $key => $value) {
            if (!empty($value)) {
                $configBuilder->setConfig($key, $value);
            }
        }

        $action = app(CreateTenant::class);

        $tenant = $action($name, $identifier, $configBuilder);

        return $tenant;
    }
}
