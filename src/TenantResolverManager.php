<?php

declare(strict_types=1);

namespace Quvel\Tenant;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Models\Tenant;

/**
 * Manages tenant resolution.
 *
 * Provides a simple, configurable approach to resolving tenants
 * from HTTP requests using a single configured strategy.
 */
class TenantResolverManager
{
    /**
     * @var TenantResolver|null The current resolver instance
     */
    protected ?TenantResolver $resolver = null;

    /**
     * Resolve tenant using the configured resolver.
     *
     * @param Request $request The HTTP request
     * @return Tenant|null The resolved tenant or null if none found
     */
    public function resolve(Request $request): ?Tenant
    {
        return $this->getResolver()->resolve($request);
    }

    /**
     * Get the configured resolver instance.
     *
     * @return TenantResolver
     * @throws InvalidArgumentException If resolver is not configured or invalid
     */
    public function getResolver(): TenantResolver
    {
        if ($this->resolver === null) {
            $this->resolver = $this->createResolver();
        }

        return $this->resolver;
    }

    /**
     * Set a custom resolver instance.
     *
     * @param TenantResolver $resolver
     * @return void
     */
    public function setResolver(TenantResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Create a resolver instance from configuration.
     *
     * @return TenantResolver
     * @throws InvalidArgumentException If resolver is not configured or invalid
     */
    protected function createResolver(): TenantResolver
    {
        $resolverClass = config('tenant.resolver');

        if (!$resolverClass) {
            throw new InvalidArgumentException("No tenant resolver configured");
        }

        if (!class_exists($resolverClass)) {
            throw new InvalidArgumentException("Resolver class does not exist: $resolverClass");
        }

        if (!is_subclass_of($resolverClass, TenantResolver::class)) {
            throw new InvalidArgumentException("Resolver must implement TenantResolver interface");
        }

        $config = config('tenant.resolver_config', []);

        return new $resolverClass($config);
    }
}