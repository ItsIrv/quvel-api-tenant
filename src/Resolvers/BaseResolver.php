<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Models\Tenant;

/**
 * Base resolver with shared caching and lookup logic.
 */
abstract class BaseResolver implements TenantResolver
{
    /**
     * Extract tenant identifier from request.
     */
    abstract protected function extractIdentifier(Request $request): ?string;

    /**
     * @var array Configuration options
     */
    protected array $config;

    /**
     * @param array $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Resolve tenant from request.
     */
    public function resolve(Request $request): ?Tenant
    {
        $identifier = $this->extractIdentifier($request);

        if (!$identifier) {
            return null;
        }

        $cacheTtl = $this->getCacheTtl();

        if ($cacheTtl > 0) {
            $cacheKey = $this->getCacheKey($request);

            return Cache::remember($cacheKey, $cacheTtl, fn() => $this->findTenant($identifier));
        }

        return $this->findTenant($identifier);
    }

    /**
     * Get cache key for this resolver and request.
     */
    public function getCacheKey(Request $request): string
    {
        $identifier = $this->extractIdentifier($request) ?? 'null';

        return 'tenant.' . $identifier;
    }

    /**
     * Get the cache TTL for this resolver.
     */
    public function getCacheTtl(): int
    {
        return $this->config['cache_ttl'] ?? 300;
    }

    /**
     * Find tenant by identifier.
     */
    protected function findTenant(string $identifier): ?Tenant
    {
        $modelClass = config('tenant.model', Tenant::class);

        return $modelClass::where('identifier', $identifier)
            ->where('is_active', true)
            ->first();
    }
}