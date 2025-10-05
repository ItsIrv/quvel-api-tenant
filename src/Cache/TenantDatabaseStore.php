<?php

namespace Quvel\Tenant\Cache;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Cache\LockProvider;
use Quvel\Tenant\Context\TenantContext;

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

        // Add tenant_id if tenant context is available
        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = app(TenantContext::class)->current();
            if ($tenant) {
                $record['tenant_id'] = $tenant->id;
            }
        }

        try {
            // Use upsert to handle duplicates properly
            return $this->table()->upsert($record, ['key'], ['value', 'expiration']);
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

        // Add tenant scoping if enabled and tenant context is available
        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = app(TenantContext::class)->current();
            if ($tenant) {
                $cache = $cache->where('tenant_id', $tenant->id);
            }
        }

        $cache = $cache->first();

        // If we don't have a value or it's expired, return null
        if (is_null($cache)) {
            return;
        }

        // If the expiration time is in the past, delete the entry and return null
        if ($this->getTime() >= $cache->expiration) {
            $this->forget($key);

            return;
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

        // Add tenant scoping if enabled and tenant context is available
        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = app(TenantContext::class)->current();
            if ($tenant) {
                $query = $query->where('tenant_id', $tenant->id);
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

        // Add tenant scoping if enabled and tenant context is available
        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = app(TenantContext::class)->current();
            if ($tenant) {
                $query = $query->where('tenant_id', $tenant->id);
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
     * @return \Illuminate\Contracts\Cache\Lock
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
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}