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
                    // Required
                    'app.name',
                    'app.url',
                    'frontend.url',
                    // Optional app
                    'app.env',
                    'app.debug',
                    'app.timezone',
                    'app.locale',
                    'app.fallback_locale',
                    // Optional frontend
                    'frontend.internal_api_url',
                    'frontend.capacitor_scheme',
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
                    // Basic fields (required)
                    'app.name',
                    'app.url',
                    'frontend.url',
                    // Basic fields (optional app)
                    'app.env',
                    'app.debug',
                    'app.timezone',
                    'app.locale',
                    'app.fallback_locale',
                    // Basic fields (optional frontend)
                    'frontend.internal_api_url',
                    'frontend.capacitor_scheme',
                    // Database fields
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
