<?php

declare(strict_types=1);

namespace Quvel\Tenant\Data;

class ConfigFieldDefinitions
{
    /**
     * Get all available configuration fields.
     */
    public static function all(): array
    {
        return [
            'app.name' => [
                'label' => 'Application Name',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'My Application',
                'description' => 'Display name for this tenant',
            ],
            'frontend.url' => [
                'label' => 'Frontend URL',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://tenant.example.com',
                'description' => 'Frontend URL for this tenant',
            ],
            'database.connections.mysql.host' => [
                'label' => 'Database Host',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'localhost',
                'description' => 'Database server hostname or IP',
            ],
            'database.connections.mysql.port' => [
                'label' => 'Database Port',
                'type' => 'number',
                'required' => false,
                'placeholder' => '3306',
                'description' => 'Database server port',
            ],
            'database.connections.mysql.database' => [
                'label' => 'Database Name',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'tenant_database',
                'description' => 'Name of the tenant database',
            ],
            'database.connections.mysql.username' => [
                'label' => 'Database Username',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'tenant_user',
                'description' => 'Database username for tenant',
            ],
            'database.connections.mysql.password' => [
                'label' => 'Database Password',
                'type' => 'password',
                'required' => true,
                'placeholder' => '',
                'description' => 'Database password for tenant',
            ],
        ];
    }

    /**
     * Get fields for specific keys.
     */
    public static function only(array $keys): array
    {
        $all = self::all();
        $result = [];

        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }
}
