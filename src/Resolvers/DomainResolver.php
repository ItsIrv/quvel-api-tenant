<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Helpers\TenantHelper;
use Quvel\Tenant\Models\Tenant;

/**
 * Resolves tenants based on the request domain.
 *
 * Looks up tenants by matching the request host against tenant identifiers.
 */
class DomainResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $host = $request->getHost();

        if (!$host) {
            return null;
        }

        $cacheKey = $this->getCacheKey($request);

        return Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($host) {
            return TenantHelper::findByIdentifier($host);
        });
    }

    public function getCacheKey(Request $request): string
    {
        return 'tenant.domain.' . $request->getHost();
    }

    public function getCacheTtl(): int
    {
        return config('tenant.resolvers.domain.cache_ttl', 300);
    }
}