<?php

declare(strict_types=1);

namespace Quvel\Tenant\Redis;

use Illuminate\Redis\Connections\Connection;

/**
 * Tenant-aware Redis Connection wrapper.
 *
 * Wraps a standard Redis connection and automatically prefixes all keys
 * with the current tenant ID to provide tenant isolation.
 */
class TenantAwareRedisConnection
{
    /**
     * The underlying Redis connection.
     */
    protected Connection $connection;

    /**
     * Redis commands that have keys as their first argument.
     */
    protected array $keyCommands = [
        'append',
        'decr',
        'decrby',
        'del',
        'dump',
        'exists',
        'expire',
        'expireat',
        'get',
        'getbit',
        'getrange',
        'getset',
        'hdel',
        'hexists',
        'hget',
        'hgetall',
        'hincrby',
        'hincrbyfloat',
        'hkeys',
        'hlen',
        'hmget',
        'hmset',
        'hset',
        'hsetnx',
        'hstrlen',
        'hvals',
        'incr',
        'incrby',
        'incrbyfloat',
        'lindex',
        'linsert',
        'llen',
        'lpop',
        'lpush',
        'lpushx',
        'lrange',
        'lrem',
        'lset',
        'ltrim',
        'mget',
        'move',
        'persist',
        'pexpire',
        'pexpireat',
        'psetex',
        'pttl',
        'rename',
        'renamenx',
        'restore',
        'rpop',
        'rpush',
        'rpushx',
        'sadd',
        'scard',
        'sdiff',
        'sdiffstore',
        'set',
        'setbit',
        'setex',
        'setnx',
        'setrange',
        'sinter',
        'sinterstore',
        'sismember',
        'smembers',
        'smove',
        'spop',
        'srandmember',
        'srem',
        'strlen',
        'sunion',
        'sunionstore',
        'ttl',
        'type',
        'zadd',
        'zcard',
        'zcount',
        'zincrby',
        'zinterstore',
        'zlexcount',
        'zrange',
        'zrangebylex',
        'zrangebyscore',
        'zrank',
        'zrem',
        'zremrangebylex',
        'zremrangebyrank',
        'zremrangebyscore',
        'zrevrange',
        'zrevrangebylex',
        'zrevrangebyscore',
        'zrevrank',
        'zscore',
        'zunionstore',
        'scan',
        'sscan',
        'hscan',
        'zscan',
    ];

    /**
     * Create a new tenant-aware Redis connection.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Dynamically pass methods to the underlying connection with tenant key prefixing.
     */
    public function __call(string $method, array $parameters)
    {
        $tenantId = tenant_id();

        if ($tenantId !== null && in_array(strtolower($method), $this->keyCommands, true)) {
            $parameters = $this->prefixKeys($parameters, $tenantId);
        }

        return $this->connection->{$method}(...$parameters);
    }

    /**
     * Prefix keys in the parameters array with tenant ID.
     */
    protected function prefixKeys(array $parameters, int $tenantId): array
    {
        if (empty($parameters)) {
            return $parameters;
        }

        if (is_string($parameters[0])) {
            $parameters[0] = $this->addTenantPrefix($parameters[0], $tenantId);
        } elseif (is_array($parameters[0])) {
            $parameters[0] = array_map(
                fn ($key) => is_string($key) ? $this->addTenantPrefix($key, $tenantId) : $key,
                $parameters[0]
            );
        }

        return $parameters;
    }

    /**
     * Add a tenant prefix to a Redis key.
     */
    protected function addTenantPrefix(string $key, int $tenantId): string
    {
        if (str_starts_with($key, "tenant_$tenantId:")) {
            return $key;
        }

        return "tenant_$tenantId:$key";
    }

    /**
     * Get the underlying Redis connection.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Pass property access to the underlying connection.
     */
    public function __get(string $property)
    {
        return $this->connection->{$property};
    }

    /**
     * Set properties on the underlying connection.
     */
    public function __set(string $property, $value)
    {
        $this->connection->{$property} = $value;
    }

    /**
     * Check if a property exists on the underlying connection.
     */
    public function __isset(string $property)
    {
        return isset($this->connection->{$property});
    }
}
