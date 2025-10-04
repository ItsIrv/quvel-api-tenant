<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Illuminate\Database\DatabaseManager;

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

        $this->setIfExists('database.default', 'database.default');
        $this->setIfExists("database.connections.$connection.host", "database.connections.$connection.host");
        $this->setIfExists("database.connections.$connection.port", "database.connections.$connection.port");
        $this->setIfExists("database.connections.$connection.database", "database.connections.$connection.database");
        $this->setIfExists("database.connections.$connection.username", "database.connections.$connection.username");
        $this->setIfExists("database.connections.$connection.password", "database.connections.$connection.password");

        $this->switchDatabaseConnection($connection);
    }

    /**
     * Switch database connection and purge existing connections.
     */
    protected function switchDatabaseConnection(string $connection): void
    {
        $dbManager = app(DatabaseManager::class);

        $connections = $dbManager->getConnections();
        if ($connections !== [] && array_key_exists($connection, $connections)) {
            $dbManager->purge($connection);
        }

        $dbManager->setDefaultConnection($connection);
        $dbManager->reconnect($connection);
    }
}