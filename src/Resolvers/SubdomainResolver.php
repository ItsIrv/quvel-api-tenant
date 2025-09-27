<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Helpers\TenantHelper;
use Quvel\Tenant\Models\Tenant;

/**
 * Resolves tenants based on the request subdomain.
 *
 * Extracts the subdomain from the host and looks up tenants by identifier.
 */
class SubdomainResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $host = $request->getHost();

        if (!$host) {
            return null;
        }

        $subdomain = $this->extractSubdomain($host);

        if (!$subdomain) {
            return null;
        }

        $cacheKey = $this->getCacheKey($request);

        return Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($subdomain) {
            return TenantHelper::findByIdentifier($subdomain);
        });
    }

    public function getCacheKey(Request $request): string
    {
        return 'tenant.subdomain.' . $this->extractSubdomain($request->getHost());
    }

    public function getCacheTtl(): int
    {
        return config('tenant.resolvers.subdomain.cache_ttl', 300);
    }

    /**
     * Extract subdomain from host.
     *
     * @param string $host The request host
     * @return string|null The subdomain or null if none found
     */
    protected function extractSubdomain(string $host): ?string
    {
        $parts = explode('.', $host);

        if (count($parts) < 3) {
            return null;
        }

        return $parts[0];
    }
}