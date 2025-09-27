<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

use Illuminate\Http\Request;
use Quvel\Tenant\Models\Tenant;

/**
 * Interface for tenant resolution strategies.
 *
 * Resolvers determine which tenant should be used for the current request
 * based on different strategies (domain, subdomain, path, header, etc.).
 */
interface TenantResolver
{
    /**
     * Resolve the tenant for the given request.
     *
     * @param Request $request The current HTTP request
     * @return Tenant|null The resolved tenant or null if none found
     */
    public function resolve(Request $request): ?Tenant;

    /**
     * Get the cache key for this resolver and request.
     *
     * @param Request $request The current HTTP request
     * @return string Cache key for storing resolved tenant
     */
    public function getCacheKey(Request $request): string;

    /**
     * Get the cache TTL for this resolver.
     *
     * @return int Cache time-to-live in seconds
     */
    public function getCacheTtl(): int;
}