<?php

declare(strict_types=1);

namespace Quvel\Tenant\Data;

class PresetDefinitions
{
    /**
     * Get all available presets.
     */
    public static function all(): array
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
                'fields' => [
                    'app.name',
                    'app.url',
                    'frontend.url',
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
                'fields' => [
                    'app.name',
                    'app.url',
                    'frontend.url',
                    'database.connections.mysql.host',
                    'database.connections.mysql.port',
                    'database.connections.mysql.database',
                    'database.connections.mysql.username',
                    'database.connections.mysql.password',
                ],
            ],
        ];
    }

    /**
     * Get a specific preset.
     */
    public static function get(string $preset): ?array
    {
        return self::all()[$preset] ?? null;
    }

    /**
     * Get preset names.
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }
}
