<?php

namespace Quvel\Tenant\Queue\Connectors;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Redis\RedisManager;
use Laravel\Horizon\Connectors\RedisConnector as HorizonRedisConnector;
use Quvel\Tenant\Queue\TenantHorizonRedisQueue;

/**
 * @property RedisManager $redis
 * @property string $connection
 */
class TenantHorizonConnector extends HorizonRedisConnector
{
    /**
     * Establish a queue connection.
     */
    public function connect(array $config): TenantHorizonRedisQueue|Queue
    {
        return new TenantHorizonRedisQueue(
            $this->redis,
            $config['queue'],
            $config['connection'] ?? $this->connection,
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null
        );
    }
}
