<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Quvel\Tenant\Contracts\ResolutionService as ResolutionServiceContract;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Events\TenantNotFound;
use Quvel\Tenant\Events\TenantResolved;

/**
 * Service for resolving tenants from requests with caching support.
 */
class ResolutionService implements ResolutionServiceContract
{
    protected $bypassCallback = null;

    public function __construct(
        protected TenantResolver $resolver
    ) {
    }

    /**
     * Resolve tenant from request.
     */
    public function resolve(Request $request): mixed
    {
        $identifier = null;

        if (method_exists($this->resolver, 'getCacheKey')) {
            $identifier = $this->resolver->getCacheKey($request);
        }

        if ($identifier && config('tenant.resolver.config.cache_enabled', true)) {
            $tenant = $this->resolveTenantWithCache($identifier, $request);
            $cacheKey = $identifier;
        } else {
            $tenant = $this->resolver->resolve($request);
            $cacheKey = null;
        }

        if ($tenant) {
            TenantResolved::dispatch(
                $tenant,
                get_class($this->resolver),
                $cacheKey
            );
        } else {
            TenantNotFound::dispatch(
                $request,
                get_class($this->resolver),
                $cacheKey,
            );
        }

        return $tenant;
    }

    /**
     * Resolve tenant with caching support.
     */
    protected function resolveTenantWithCache(string $cacheKey, Request $request)
    {
        $cacheTtl = config('tenant.resolver.config.cache_ttl', 0);

        if ($cacheTtl > 0) {
            return Cache::remember(
                "tenant.$cacheKey",
                $cacheTtl,
                fn() => $this->resolver->resolve($request)
            );
        }

        return $this->resolver->resolve($request);
    }

    /**
     * Set a callback to determine if a tenant resolution should be bypassed.
     *
     * @param callable|null $callback Receives Request, returns bool
     */
    public function setBypassCallback(?callable $callback): void
    {
        $this->bypassCallback = $callback;
    }

    /**
     * Check if the tenant resolution should be bypassed for this request.
     */
    public function shouldBypass(Request $request): bool
    {
        if ($this->bypassCallback === null) {
            return false;
        }

        return ($this->bypassCallback)($request);
    }
}
