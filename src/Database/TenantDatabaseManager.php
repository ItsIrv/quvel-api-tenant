<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Quvel\Tenant\Context\TenantContext;

/**
 * Tenant-aware database manager.
 *
 * This manager extends Laravel's DatabaseManager to provide tenant-specific
 * default connections that are determined at runtime rather than cached at construction time.
 */
class TenantDatabaseManager extends DatabaseManager
{
    public function __construct(
        $app,
        ConnectionFactory $factory,
        protected TenantContext $tenantContext
    ) {
        parent::__construct($app, $factory);
    }

    /**
     * Get the default connection name.
     *
     * Overridden to return a tenant-specific connection name when tenant context is available.
     */
    public function getDefaultConnection()
    {
        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return parent::getDefaultConnection();
        }

        $hasDbOverride = $tenant->hasConfig('database.default') ||
            $tenant->hasConfig('database.connections.mysql.host') ||
            $tenant->hasConfig('database.connections.mysql.database');

        if (!$hasDbOverride) {
            return parent::getDefaultConnection();
        }

        $baseConnection = $tenant->getConfig('database.default')
            ?? config('database.default')
            ?? 'mysql';

        return $this->getTenantConnectionName($tenant, $baseConnection);
    }

    /**
     * Get the tenant connection name.
     *
     * This matches the naming convention in DatabaseConfigPipe.
     */
    protected function getTenantConnectionName($tenant, string $baseConnection): string
    {
        return 'tenant_' . $tenant->id;
    }
}
