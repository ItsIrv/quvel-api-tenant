<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Illuminate\Support\Facades\DB;

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

        $connection = $this->tenant->getConfig('database.default') ?? 'mysql';

        $this->setMany([
            'database.default' => 'database.default',
            "database.connections.$connection.host",
            "database.connections.$connection.port",
            "database.connections.$connection.database",
            "database.connections.$connection.username",
            "database.connections.$connection.password",
        ]);

        $tenantConnection = $this->createTenantConnection($connection);

        if ($tenantConnection) {
            DB::setDefaultConnection($tenantConnection);
        }
    }

    /**
     * Create tenant-specific database connection.
     */
    protected function createTenantConnection(string $baseConnection): ?string
    {
        $tenantConnectionName = 'tenant_' . $this->tenant->id;

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
}