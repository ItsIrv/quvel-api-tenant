<?php

declare(strict_types=1);

namespace Quvel\Tenant\Redis;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\RedisManager;

/**
 * Tenant-aware Redis Manager.
 *
 * Wraps Laravel's RedisManager to automatically prefix all Redis keys
 * with the current tenant ID when a tenant context exists.
 */
class TenantAwareRedisManager extends RedisManager
{
    /**
     * Get a Redis connection instance.
     */
    public function connection($name = null): Connection|TenantAwareRedisConnection
    {
        $connection = parent::connection($name);

        if ($name === 'horizon' || ($name === null && config('horizon.use') === config('database.redis.default'))) {
            return $this->wrapWithTenantContext($connection);
        }

        return $connection;
    }

    /**
     * Wrap a Redis connection with the tenant context.
     */
    protected function wrapWithTenantContext(Connection $connection): TenantAwareRedisConnection
    {
        return new TenantAwareRedisConnection($connection);
    }
}
