<?php

declare(strict_types=1);

namespace Quvel\Tenant\Cache\Concerns;

use Illuminate\Support\Facades\Cache;
use Quvel\Tenant\Concerns\TenantAware as BaseTenantAware;

/**
 * Trait for making cache operations optionally tenant-aware.
 *
 * This trait provides helper methods for working with tenant-scoped cache keys
 * while preserving the standard Laravel cache interface.
 *
 * Usage Options:
 *
 * 1. Basic tenant-scoped keys:
 *    Cache::put($this->tenantKey('user.settings'), $data, 3600);
 *    $data = Cache::get($this->tenantKey('user.settings'));
 *
 * 2. Multiple tenant-scoped keys:
 *    $keys = $this->tenantKeys(['config', 'settings', 'permissions']);
 *    Cache::many($keys);
 *
 * 3. Conditional tenant scoping:
 *    $key = $this->hasTenantContext()
 *        ? $this->tenantKey('config')
 *        : 'global.config';
 *    Cache::put($key, $data);
 *
 * 4. Remember pattern with tenant scope:
 *    return Cache::remember($this->tenantKey('expensive.calculation'), 3600, function() {
 *        return $this->performExpensiveCalculation();
 *    });
 */
trait HasTenantCache
{
    use BaseTenantAware;

    /**
     * Get a tenant-scoped cache key.
     */
    protected function tenantKey(string $key): string
    {
        return $this->getTenantScopedName($key);
    }

    /**
     * Get multiple tenant-scoped cache keys.
     */
    protected function tenantKeys(array $keys): array
    {
        return array_map([$this, 'tenantKey'], $keys);
    }

    /**
     * Get tenant-scoped cache tags.
     */
    protected function tenantTags(array $tags = []): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return $tags;
        }

        $tenantTag = 'tenant.' . $tenant->public_id;

        return array_merge([$tenantTag], $tags);
    }

    /**
     * Get a tenant-specific cache store name.
     */
    protected function tenantStore(): ?string
    {
        return $this->getCurrentTenant()?->getConfig('cache.default');
    }

    /**
     * Get tenant-specific cache TTL.
     */
    protected function tenantTtl(int $default = 3600): int
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return $default;
        }

        return $tenant->getConfig('cache.default_ttl', $default);
    }

    /**
     * Flush all cache for the current tenant.
     * Use with caution - this will clear ALL tenant cache.
     */
    protected function flushTenantCache(): bool
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return false;
        }

        // If using tags, flush by tenant tag
        if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
            return Cache::tags(['tenant.' . $tenant->public_id])->flush();
        }

        // For other drivers, we can't easily flush just tenant keys
        // This would require iterating through all keys which isn't practical
        return false;
    }
}
