<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Quvel\Tenant\Http\Resources\TenantConfigResource;

/**
 * Action for SSR to fetch all tenants for cache warming.
 * Returns all active tenants with their protected config.
 */
class TenantsCache
{
    private const string CACHE_KEY = 'tenant_dump_all';

    public function __construct(
        protected CacheRepository $cache,
        protected ConfigRepository $config
    ) {
    }

    /**
     * Execute the action.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        $tenants = $this->getTenants();

        $collection = TenantConfigResource::collection($tenants);
        $collection->each(fn($resource) => $resource->setVisibilityLevel('protected'));

        return $collection;
    }

    /**
     * Get all active tenants with caching.
     */
    protected function getTenants(): Collection
    {
        if (app()->environment('local')) {
            return $this->fetchAllTenants();
        }

        $cacheTtl = $this->config->get('tenant.resolver.config.cache_ttl', 300);

        return $this->cache->remember(
            self::CACHE_KEY,
            $cacheTtl,
            fn (): Collection => $this->fetchAllTenants()
        );
    }

    /**
     * Fetch all active tenants from database.
     */
    protected function fetchAllTenants(): Collection
    {
        $tenantModel = $this->config->get('tenant.model');

        return $tenantModel::where('is_active', true)->get();
    }
}