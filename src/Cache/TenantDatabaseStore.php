<?php

namespace Quvel\Tenant\Cache;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Quvel\Tenant\Facades\TenantContext;

class TenantDatabaseStore extends DatabaseStore implements LockProvider
{
    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     */
    public function put($key, $value, $seconds)
    {
        $key = $this->prefix . $key;
        $value = $this->serialize($value);
        $expiration = $this->getTime() + $seconds;

        $record = [
            'key' => $key,
            'value' => $value,
            'expiration' => $expiration,
        ];

        if (config('tenant.cache.auto_tenant_id', false) && TenantContext::needsTenantIdScope()) {
            $tenant = TenantContext::current();
            $record['tenant_id'] = $tenant ? $tenant->id : null;
        }

        try {
            return (bool)$this->table()->upsert($record, ['key'], ['value', 'expiration']);
        } catch (Exception $exception) {
            $this->handleWriteException($exception);

            return false;
        }
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        $prefixed = $this->prefix . $key;

        $cache = $this->table()->where('key', '=', $prefixed);

        if (config('tenant.cache.auto_tenant_id', false) && TenantContext::needsTenantIdScope()) {
            $tenant = TenantContext::current();
            $cache = $tenant
                ? $cache->where('tenant_id', $tenant->id)
                : $cache->whereNull('tenant_id'); // For operations without tenant context, only show system-level cache
        }

        $cache = $cache->first();

        if (is_null($cache)) {
            return null;
        }

        if ($this->getTime() >= $cache->expiration) {
            $this->forget($key);

            return null;
        }

        return $this->unserialize($cache->value);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     */
    public function forget($key): int
    {
        $query = $this->table()->where('key', '=', $this->prefix . $key);

        if (config('tenant.cache.auto_tenant_id', false) && TenantContext::needsTenantIdScope()) {
            $tenant = TenantContext::current();
            $query = $tenant
                ? $query->where('tenant_id', $tenant->id)
                : $query->whereNull(
                    'tenant_id'
                ); // For operations without tenant context, only affect system-level cache
        }

        return $query->delete();
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): int
    {
        $query = $this->table();

        if (config('tenant.cache.auto_tenant_id', false) && TenantContext::needsTenantIdScope()) {
            $tenant = TenantContext::current();
            $query = $tenant
                ? $query->where('tenant_id', $tenant->id)
                : $query->whereNull(
                    'tenant_id'
                ); // For operations without tenant context, only flush system-level cache
        }

        return $query->delete();
    }

    /**
     * Get a lock instance.
     *
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return Lock
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        /** @psalm-suppress ArgumentTypeCoercion Laravel DatabaseLock expects concrete Connection but parent uses ConnectionInterface */
        return new TenantDatabaseLock(
            $this->connection,
            $this->lockTable,
            $this->prefix . $name,
            $seconds,
            $owner
        );
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param string $name
     * @param string $owner
     * @return Lock
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}
