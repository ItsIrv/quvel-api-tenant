<?php

declare(strict_types=1);

namespace Quvel\Tenant\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Quvel\Tenant\Contracts\TenantContext;

/**
 * Tenant-aware cache manager that automatically scopes cache operations to tenants.
 *
 * This manager extends Laravel's CacheManager to provide automatic tenant-aware
 * cache scoping when enabled. It modifies cache operations to include tenant
 * context automatically.
 *
 * Features:
 * - Automatic key prefixing with tenant scope
 * - Tenant-specific cache stores when configured
 * - Preserves all standard Laravel cache functionality
 * - Configurable enable/disable via config
 */
class TenantCacheManager extends CacheManager
{
    public function __construct(Application $app, protected TenantContext $tenantContext)
    {
        parent::__construct($app);
    }

    /**
     * Get a cache store instance with tenant awareness.
     */
    public function store($name = null): Repository
    {
        $store = parent::store($name);

        if (!config('tenant.cache.auto_tenant_scoping', false)) {
            return $store;
        }

        $tenant = $this->tenantContext->current();
        if (!$tenant) {
            return $store;
        }

        $tenantStore = new TenantCacheStore($store->getStore(), $tenant);

        return new CacheRepository($tenantStore);
    }

    /**
     * Get the default cache store name for the current tenant.
     */
    public function getDefaultDriver()
    {
        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return parent::getDefaultDriver();
        }

        $tenantDriver = $tenant->getConfig('cache.default');

        return $tenantDriver ?: parent::getDefaultDriver();
    }
}
