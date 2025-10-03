<?php

declare(strict_types=1);

namespace Quvel\Tenant\Managers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Quvel\Tenant\Concerns\TenantResolver;
use Quvel\Tenant\Models\Tenant;

/**
 * Manages tenant resolution, caching, and context.
 *
 * Handles the complete tenant resolution flow:
 * 1. Extract identifier from request via resolver
 * 2. Cache and lookup tenant from database
 * 3. Manage current tenant context
 */
class TenantResolverManager
{
    protected readonly TenantResolver $resolver;

    public function __construct()
    {
        $this->resolver = $this->createResolver();
    }

    /**
     * Resolve tenant from request.
     */
    public function resolveTenant(Request $request): ?Tenant
    {
        if (method_exists($this->resolver, 'getCacheKey')) {
            $cacheKey = $this->resolver->getCacheKey($request);

            if ($cacheKey) {
                return $this->resolveTenantWithCache($cacheKey, $this->resolver, $request);
            }
        }

        return $this->resolver->resolve($request);
    }

    /**
     * Resolve tenant with caching support.
     */
    protected function resolveTenantWithCache(string $cacheKey, TenantResolver $resolver, Request $request): ?Tenant
    {
        $cacheTtl = config('tenant.resolver.config.cache_ttl', 0);

        if ($cacheTtl > 0) {
            return Cache::remember(
                "tenant.$cacheKey",
                $cacheTtl,
                static fn() => $resolver->resolve($request)
            );
        }

        return $resolver->resolve($request);
    }

    /**
     * Get the resolver instance.
     */
    public function getResolver(): TenantResolver
    {
        return $this->resolver;
    }

    /**
     * Create a resolver instance from configuration.
     */
    protected function createResolver(): TenantResolver
    {
        $resolverClass = config('tenant.resolver.class');
        $config = config('tenant.resolver.config', []);

        if (!class_exists($resolverClass)) {
            throw new InvalidArgumentException("Resolver class not found: $resolverClass");
        }

        if (!is_subclass_of($resolverClass, TenantResolver::class)) {
            throw new InvalidArgumentException("Resolver must implement TenantResolver interface");
        }

        return new $resolverClass($config);
    }
}