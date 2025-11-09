<?php

namespace Quvel\Tenant\Queue\Connectors;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\RedisConnector;
use Quvel\Tenant\Queue\TenantRedisQueue;

class TenantRedisConnector extends RedisConnector
{
    /**
     * Establish a queue connection.
     */
    public function connect(array $config): TenantRedisQueue|Queue
    {
        return new TenantRedisQueue(
            $this->redis,
            $config['queue'],
            $config['connection'] ?? $this->connection,
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null,
            $config['after_commit'] ?? null,
            $config['backoff'] ?? null
        );
    }
}
