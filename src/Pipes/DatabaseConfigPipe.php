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
            sprintf('database.connections.%s.host', $connection),
            sprintf('database.connections.%s.port', $connection),
            sprintf('database.connections.%s.database', $connection),
            sprintf('database.connections.%s.username', $connection),
            sprintf('database.connections.%s.password', $connection),
            'tenant.scoping.skip_when_isolated',
        ]);

        $this->createTenantConnection($connection);
    }

    /**
     * Create a tenant-specific database connection.
     */
    protected function createTenantConnection(string $baseConnection): ?string
    {
        $tenantConnectionName = $this->getTenantConnectionName($baseConnection);

        if (config('database.connections.' . $tenantConnectionName)) {
            return $tenantConnectionName;
        }

        $baseConfig = config('database.connections.' . $baseConnection, []);
        $tenantConfig = $baseConfig;

        if ($this->tenant->hasConfig(sprintf('database.connections.%s.host', $baseConnection))) {
            $tenantConfig['host'] = $this->tenant->getConfig(sprintf('database.connections.%s.host', $baseConnection));
        }

        if ($this->tenant->hasConfig(sprintf('database.connections.%s.port', $baseConnection))) {
            $tenantConfig['port'] = $this->tenant->getConfig(sprintf('database.connections.%s.port', $baseConnection));
        }

        if ($this->tenant->hasConfig(sprintf('database.connections.%s.database', $baseConnection))) {
            $tenantConfig['database'] = $this->tenant->getConfig(
                sprintf('database.connections.%s.database', $baseConnection)
            );
        }

        if ($this->tenant->hasConfig(sprintf('database.connections.%s.username', $baseConnection))) {
            $tenantConfig['username'] = $this->tenant->getConfig(
                sprintf('database.connections.%s.username', $baseConnection)
            );
        }

        if ($this->tenant->hasConfig(sprintf('database.connections.%s.password', $baseConnection))) {
            $tenantConfig['password'] = $this->tenant->getConfig(
                sprintf('database.connections.%s.password', $baseConnection)
            );
        }

        config(['database.connections.' . $tenantConnectionName => $tenantConfig]);

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
        return $this->applyConfigurator('default_connection', config('database.default') ?? 'mysql');
    }

    /**
     * Get the tenant connection name using configurator or default.
     */
    protected function getTenantConnectionName(string $baseConnection): string
    {
        if ($this->hasConfigurator('tenant_connection_name')) {
            return static::$configurators['tenant_connection_name']($this, $baseConnection);
        }

        if (config('tenant.database.pool_connections_by_host', false)) {
            return $this->getPooledConnectionName($baseConnection);
        }

        return 'tenant_' . $this->tenant->id;
    }

    /**
     * Generate a pooled connection name based on host:port hash.
     */
    protected function getPooledConnectionName(string $baseConnection): string
    {
        $host = $this->tenant->getConfig(sprintf('database.connections.%s.host', $baseConnection))
            ?? config(sprintf('database.connections.%s.host', $baseConnection));
        $port = $this->tenant->getConfig(sprintf('database.connections.%s.port', $baseConnection))
            ?? config(sprintf('database.connections.%s.port', $baseConnection));

        return 'tenant_' . md5($host . ':' . $port);
    }
}
