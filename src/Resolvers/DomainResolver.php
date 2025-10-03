<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;
use Quvel\Tenant\Concerns\TenantResolver;
use Quvel\Tenant\Models\Tenant;

/**
 * Resolves tenant identifiers from request domain.
 *
 * Simply extracts the host from the request - caching and tenant
 * lookup are handled by the TenantResolverManager.
 */
class DomainResolver implements TenantResolver
{
    /**
     * @param array $config Configuration options
     */
    public function __construct(protected array $config = [])
    {
    }

    /**
     * Resolve tenant from request domain.
     */
    public function resolve(Request $request): ?Tenant
    {
        $identifier = $request->getHost();

        if (!$identifier) {
            return null;
        }

        return Tenant::findByIdentifier($identifier);
    }

    /**
     * Get cache key for the request (enables manager-level caching).
     */
    public function getCacheKey(Request $request): ?string
    {
        return $request->getHost();
    }
}