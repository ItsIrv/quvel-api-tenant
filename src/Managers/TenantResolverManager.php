<?php

declare(strict_types=1);

namespace Quvel\Tenant\Managers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Models\Tenant;

/**
 * Manages tenant resolution, and caching.
 */
class TenantResolverManager
{
    protected readonly TenantResolver $resolver;
    protected $bypassCallback = null;

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

    /**
     * Set a callback to determine if tenant resolution should be bypassed.
     *
     * @param callable|null $callback Receives Request, returns bool
     */
    public function setBypassCallback(?callable $callback): void
    {
        $this->bypassCallback = $callback;
    }

    /**
     * Check if tenant resolution should be bypassed for this request.
     */
    public function shouldBypass(Request $request): bool
    {
        if ($this->bypassCallback === null) {
            return false;
        }

        return ($this->bypassCallback)($request);
    }
}