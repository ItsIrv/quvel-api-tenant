<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Helpers\TenantHelper;
use Quvel\Tenant\Models\Tenant;

/**
 * Resolves tenants based on HTTP header value.
 *
 * Looks for tenant identifier in a specific HTTP header.
 */
class HeaderResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $headerName = $this->getHeaderName();
        $identifier = $request->header($headerName);

        if (!$identifier) {
            return null;
        }

        $cacheKey = $this->getCacheKey($request);

        return Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($identifier) {
            return TenantHelper::findByIdentifier($identifier);
        });
    }

    public function getCacheKey(Request $request): string
    {
        $headerName = $this->getHeaderName();
        $identifier = $request->header($headerName) ?? 'null';

        return 'tenant.header.' . $identifier;
    }

    public function getCacheTtl(): int
    {
        return config('tenant.resolvers.header.cache_ttl', 300);
    }

    /**
     * Get the header name to check for tenant identifier.
     *
     * @return string The header name
     */
    protected function getHeaderName(): string
    {
        return config('tenant.resolvers.header.header_name', 'X-Tenant-ID');
    }
}