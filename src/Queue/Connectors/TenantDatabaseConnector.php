<?php

namespace Quvel\Tenant\Queue\Connectors;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Quvel\Tenant\Queue\TenantDatabaseQueue;

class TenantDatabaseConnector extends DatabaseConnector
{
    /**
     * Establish a queue connection.
     */
    public function connect(array $config): TenantDatabaseQueue|Queue
    {
        return new TenantDatabaseQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60
        );
    }
}
