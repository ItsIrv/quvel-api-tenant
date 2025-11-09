<?php

declare(strict_types=1);

namespace Quvel\Tenant\Cache;

use Illuminate\Contracts\Cache\Store;
use Quvel\Tenant\Models\Tenant;

/**
 * Tenant-aware cache store wrapper.
 *
 * This class wraps any cache store to provide automatic tenant scoping
 * for cache operations. It modifies cache keys to include tenant context
 * while preserving the original store's functionality.
 */
class TenantCacheStore implements Store
{
    public function __construct(protected Store $store, protected Tenant $tenant)
    {
    }

    /**
     * Get tenant-scoped cache key.
     */
    protected function getTenantKey(string $key): string
    {
        return sprintf('tenant.%s.%s', $this->tenant->public_id, $key);
    }

    /**
     * Get tenant-scoped cache keys for array operations.
     */
    protected function getTenantKeys(array $keys): array
    {
        return array_map([$this, 'getTenantKey'], $keys);
    }

    /**
     * Retrieve an item from the cache by key.
     */
    public function get($key)
    {
        return $this->store->get($this->getTenantKey($key));
    }

    /**
     * Retrieve multiple items from the cache by key.
     */
    public function many(array $keys)
    {
        $tenantKeys = $this->getTenantKeys($keys);
        $results = $this->store->many($tenantKeys);

        // Re-map results back to original keys
        $mapped = [];
        foreach ($keys as $originalKey) {
            $tenantKey = $this->getTenantKey($originalKey);
            if (isset($results[$tenantKey])) {
                $mapped[$originalKey] = $results[$tenantKey];
            }
        }

        return $mapped;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public function put($key, $value, $seconds)
    {
        return $this->store->put($this->getTenantKey($key), $value, $seconds);
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     */
    public function putMany(array $values, $seconds)
    {
        $tenantValues = [];
        foreach ($values as $key => $value) {
            $tenantValues[$this->getTenantKey($key)] = $value;
        }

        return $this->store->putMany($tenantValues, $seconds);
    }

    /**
     * Increment the value of an item in the cache.
     */
    public function increment($key, $value = 1)
    {
        return $this->store->increment($this->getTenantKey($key), $value);
    }

    /**
     * Decrement the value of an item in the cache.
     */
    public function decrement($key, $value = 1)
    {
        return $this->store->decrement($this->getTenantKey($key), $value);
    }

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever($key, $value)
    {
        return $this->store->forever($this->getTenantKey($key), $value);
    }

    /**
     * Remove an item from the cache.
     */
    public function forget($key)
    {
        return $this->store->forget($this->getTenantKey($key));
    }

    /**
     * Remove all items from the cache.
     * This only flushes the entire cache, not just tenant items.
     */
    public function flush()
    {
        return $this->store->flush();
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix()
    {
        return $this->store->getPrefix();
    }

    /**
     * Pass through any other method calls to the underlying store.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->store->{$method}(...$parameters);
    }
}
