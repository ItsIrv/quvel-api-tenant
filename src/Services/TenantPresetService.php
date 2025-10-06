<?php

declare(strict_types=1);

namespace Quvel\Tenant\Services;

use Quvel\Tenant\Actions\CreateTenant;
use Quvel\Tenant\Builders\TenantConfigurationBuilder;
use Quvel\Tenant\Models\Tenant;

class TenantPresetService
{
    /**
     * Get all available presets with their descriptions.
     */
    public function getAvailablePresets(): array
    {
        return [
            'basic' => [
                'name' => 'Basic',
                'description' => 'Shared database with tenant_id scoping. Quick setup for development or small applications.',
                'features' => [
                    'Shared database',
                    'Automatic tenant scoping',
                    'Basic configuration',
                ],
            ],
            'isolated_database' => [
                'name' => 'Isolated Database',
                'description' => 'Dedicated database per tenant. Complete data isolation and security.',
                'features' => [
                    'Dedicated database per tenant',
                    'Complete data isolation',
                    'Custom database credentials',
                    'Scalable architecture',
                ],
            ],
        ];
    }

    /**
     * Get just the preset names.
     */
    public function getPresetNames(): array
    {
        return array_keys($this->getAvailablePresets());
    }

    /**
     * Get form fields for a specific preset.
     */
    public function getPresetFields(string $preset): ?array
    {
        return match ($preset) {
            'basic' => $this->getBasicPresetFields(),
            'isolated_database' => $this->getIsolatedDatabasePresetFields(),
            default => null,
        };
    }

    /**
     * Create a tenant with the specified preset configuration.
     */
    public function createTenantWithPreset(string $name, string $identifier, string $preset, array $config = []): Tenant
    {
        $configBuilder = new TenantConfigurationBuilder();

        // Get the processed config for this preset
        $presetConfig = $this->buildPresetConfig($preset, $config);

        // Loop through and set each config value using the builder
        foreach ($presetConfig as $key => $value) {
            $this->setNestedConfig($configBuilder, $key, $value);
        }

        return app(CreateTenant::class)->execute(
            name: $name,
            identifier: $identifier,
            configBuilder: $configBuilder
        );
    }

    /**
     * Get form fields for basic preset.
     */
    private function getBasicPresetFields(): array
    {
        return [
            [
                'name' => 'app.name',
                'label' => 'Application Name',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'My Application',
                'description' => 'Display name for this tenant',
            ],
            [
                'name' => 'frontend.url',
                'label' => 'Frontend URL',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://tenant.example.com',
                'description' => 'Frontend URL for this tenant',
            ],
        ];
    }

    /**
     * Get form fields for isolated database preset.
     */
    private function getIsolatedDatabasePresetFields(): array
    {
        return [
            ...$this->getBasicPresetFields(),
            [
                'name' => 'database.connections.mysql.host',
                'label' => 'Database Host',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'localhost',
                'description' => 'Database server hostname or IP',
            ],
            [
                'name' => 'database.connections.mysql.database',
                'label' => 'Database Name',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'tenant_database',
                'description' => 'Name of the tenant database',
            ],
            [
                'name' => 'database.connections.mysql.username',
                'label' => 'Database Username',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'tenant_user',
                'description' => 'Database username for tenant',
            ],
            [
                'name' => 'database.connections.mysql.password',
                'label' => 'Database Password',
                'type' => 'password',
                'required' => true,
                'placeholder' => '',
                'description' => 'Database password for tenant',
            ],
        ];
    }

    /**
     * Build configuration array for the specified preset.
     */
    private function buildPresetConfig(string $preset, array $config): array
    {
        return match ($preset) {
            'basic' => $this->buildBasicConfig($config),
            'isolated_database' => $this->buildIsolatedDatabaseConfig($config),
            default => [],
        };
    }

    /**
     * Build configuration for basic preset.
     */
    private function buildBasicConfig(array $config): array
    {
        // Filter out empty values and convert dot notation to nested arrays
        $filtered = array_filter($config, fn($value) => !empty($value));
        return $this->convertDotNotationToNestedArray($filtered);
    }

    /**
     * Build configuration for isolated database preset.
     */
    private function buildIsolatedDatabaseConfig(array $config): array
    {
        // Filter out empty values and convert port to integer if provided
        $result = array_filter($config, fn($value) => !empty($value));

        if (isset($result['database.connections.mysql.port'])) {
            $result['database.connections.mysql.port'] = (int) $result['database.connections.mysql.port'];
        }

        return $this->convertDotNotationToNestedArray($result);
    }

    /**
     * Convert dot notation config keys to nested arrays.
     */
    private function convertDotNotationToNestedArray(array $config): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            data_set($result, $key, $value);
        }

        return $result;
    }

    /**
     * Recursively set nested config values using the configuration builder.
     */
    private function setNestedConfig(TenantConfigurationBuilder $builder, string|int $key, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $nestedKey => $nestedValue) {
                $this->setNestedConfig($builder, $key . '.' . $nestedKey, $nestedValue);
            }
        } else {
            $builder->setConfig($key, $value);
        }
    }
}