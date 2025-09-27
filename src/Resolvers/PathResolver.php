<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Helpers\TenantHelper;
use Quvel\Tenant\Models\Tenant;

/**
 * Resolves tenants based on URL path segment.
 *
 * Extracts tenant identifier from a specific URL segment position.
 */
class PathResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $segment = $this->getSegmentPosition();
        $identifier = $request->segment($segment);

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
        $segment = $this->getSegmentPosition();
        $identifier = $request->segment($segment) ?? 'null';

        return 'tenant.path.' . $identifier;
    }

    public function getCacheTtl(): int
    {
        return config('tenant.resolvers.path.cache_ttl', 300);
    }

    /**
     * Get the URL segment position to check for tenant identifier.
     *
     * @return int The segment position (1-based)
     */
    protected function getSegmentPosition(): int
    {
        return config('tenant.resolvers.path.segment', 1);
    }
}