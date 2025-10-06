<?php

namespace Quvel\Tenant\Cache;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Quvel\Tenant\Facades\TenantContext;
use RuntimeException;

class TenantDatabaseStore extends DatabaseStore implements LockProvider
{
    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds)
    {
        $key = $this->prefix.$key;
        $value = $this->serialize($value);
        $expiration = $this->getTime() + $seconds;

        $record = [
            'key' => $key,
            'value' => $value,
            'expiration' => $expiration,
        ];

        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = TenantContext::current();

            if ($tenant) {
                $record['tenant_id'] = $tenant->id;
            } else {
                throw new RuntimeException('Cache operation requires tenant context but none is available.');
            }
        }

        try {
            return (bool) $this->table()->upsert($record, ['key'], ['value', 'expiration']);
        } catch (Exception $e) {
            $this->handleWriteException($e);

            return false;
        }
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key)
    {
        $prefixed = $this->prefix.$key;

        $cache = $this->table()->where('key', '=', $prefixed);

        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = TenantContext::current();
            if ($tenant) {
                $cache = $cache->where('tenant_id', $tenant->id);
            } else {
                throw new RuntimeException('Cache operation requires tenant context but none is available.');
            }
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
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        $query = $this->table()->where('key', '=', $this->prefix.$key);

        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = TenantContext::current();
            if ($tenant) {
                $query = $query->where('tenant_id', $tenant->id);
            } else {
                throw new RuntimeException('Cache operation requires tenant context but none is available.');
            }
        }

        return $query->delete();
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $query = $this->table();

        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = TenantContext::current();

            if ($tenant) {
                $query = $query->where('tenant_id', $tenant->id);
            } else {
                throw new RuntimeException('Cache operation requires tenant context but none is available.');
            }
        }

        return $query->delete();
    }

    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return Lock
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        return new TenantDatabaseLock(
            $this->connection, $this->lockTable, $this->prefix.$name, $seconds, $owner
        );
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return Lock
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}