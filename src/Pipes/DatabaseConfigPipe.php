<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;

/**
 * Handles database configuration for tenants.
 */
class DatabaseConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $hasDbOverride = $this->tenant->hasConfig('database.default') ||
            $this->tenant->hasConfig('database.connections.mysql.host') ||
            $this->tenant->hasConfig('database.connections.mysql.database');

        if (!$hasDbOverride) {
            return;
        }

        $connection = $this->tenant->getConfig('database.default') ?? $this->getDefaultConnection();

        $this->setMany([
            "database.connections.$connection.host",
            "database.connections.$connection.port",
            "database.connections.$connection.database",
            "database.connections.$connection.username",
            "database.connections.$connection.password",
        ]);

        $this->createTenantConnection($connection);
    }

    /**
     * Create a tenant-specific database connection.
     */
    protected function createTenantConnection(string $baseConnection): ?string
    {
        $tenantConnectionName = $this->getTenantConnectionName($baseConnection);

        if (config("database.connections.$tenantConnectionName")) {
            return $tenantConnectionName;
        }

        $baseConfig = config("database.connections.$baseConnection", []);
        $tenantConfig = $baseConfig;

        if ($this->tenant->hasConfig("database.connections.$baseConnection.host")) {
            $tenantConfig['host'] = $this->tenant->getConfig("database.connections.$baseConnection.host");
        }

        if ($this->tenant->hasConfig("database.connections.$baseConnection.port")) {
            $tenantConfig['port'] = $this->tenant->getConfig("database.connections.$baseConnection.port");
        }

        if ($this->tenant->hasConfig("database.connections.$baseConnection.database")) {
            $tenantConfig['database'] = $this->tenant->getConfig("database.connections.$baseConnection.database");
        }

        if ($this->tenant->hasConfig("database.connections.$baseConnection.username")) {
            $tenantConfig['username'] = $this->tenant->getConfig("database.connections.$baseConnection.username");
        }

        if ($this->tenant->hasConfig("database.connections.$baseConnection.password")) {
            $tenantConfig['password'] = $this->tenant->getConfig("database.connections.$baseConnection.password");
        }

        config(["database.connections.$tenantConnectionName" => $tenantConfig]);

        return $tenantConnectionName;
    }

    /**
     * Configure a default connection type.
     */
    public static function withDefaultConnection(Closure $callback): string
    {
        static::registerConfigurator('default_connection', $callback);

        return static::class;
    }

    /**
     * Configure tenant connection name generator.
     */
    public static function withTenantConnectionName(Closure $callback): string
    {
        static::registerConfigurator('tenant_connection_name', $callback);

        return static::class;
    }

    /**
     * Get the default connection using configurator or default.
     */
    protected function getDefaultConnection(): string
    {
        return $this->applyConfigurator('default_connection', 'mysql');
    }

    /**
     * Get the tenant connection name using configurator or default.
     */
    protected function getTenantConnectionName(string $baseConnection): string
    {
        if ($this->hasConfigurator('tenant_connection_name')) {
            return static::$configurators['tenant_connection_name']($this, $baseConnection);
        }

        return 'tenant_' . $this->tenant->id;
    }
}
