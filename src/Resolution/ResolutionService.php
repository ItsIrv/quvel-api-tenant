<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolution;

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
    protected $bypassCallback;

    public function __construct(
        protected TenantResolver $resolver,
        protected ResolverManager $manager
    ) {
    }

    /**
     * Resolve tenant from request.
     */
    public function resolve(Request $request): mixed
    {
        $resolvers = config('tenant.resolver.resolvers');
        $identifier = $this->resolver->getIdentifier($request);
        $resolverUsed = $this->resolver;

        if ($identifier === null && count($resolvers) > 1) {
            for ($i = 1, $iMax = count($resolvers); $i < $iMax; $i++) {
                $resolverConfig = $resolvers[$i];
                [$driver, $config] = [array_key_first($resolverConfig), reset($resolverConfig)];

                if ($driver === null) {
                    continue;
                }

                $resolver = $this->manager->makeResolver($driver, $config);
                $identifier = $resolver->getIdentifier($request);

                if ($identifier !== null) {
                    $resolverUsed = $resolver;
                    break;
                }
            }
        }

        if ($identifier === null) {
            TenantNotFound::dispatch($request, $this->resolver::class, null);

            return null;
        }

        if (config('tenant.resolver.config.cache_enabled', true)) {
            $tenant = $this->resolveTenantWithCache($identifier, $request, $resolverUsed);
            $cacheKey = $identifier;
        } else {
            $tenant = $resolverUsed->resolve($request);
            $cacheKey = null;
        }

        if ($tenant) {
            TenantResolved::dispatch($tenant, $resolverUsed::class, $cacheKey);
        } else {
            TenantNotFound::dispatch($request, $resolverUsed::class, $cacheKey);
        }

        return $tenant;
    }

    /**
     * Resolve tenant with caching support.
     */
    protected function resolveTenantWithCache(string $cacheKey, Request $request, TenantResolver $resolver)
    {
        $cacheTtl = config('tenant.resolver.config.cache_ttl', 0);

        if ($cacheTtl > 0) {
            return Cache::remember(
                'tenant.' . $cacheKey,
                $cacheTtl,
                static fn (): mixed => $resolver->resolve($request)
            );
        }

        return $resolver->resolve($request);
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
