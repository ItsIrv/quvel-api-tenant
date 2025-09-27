<?php

declare(strict_types=1);

namespace Quvel\Tenant;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Models\Tenant;
use Quvel\Tenant\Resolvers\DomainResolver;
use Quvel\Tenant\Resolvers\HeaderResolver;
use Quvel\Tenant\Resolvers\PathResolver;
use Quvel\Tenant\Resolvers\SubdomainResolver;

/**
 * Manages tenant resolution strategies.
 *
 * Coordinates different resolver types and provides a unified interface
 * for resolving tenants from HTTP requests.
 */
class TenantResolverManager
{
    /**
     * @var array<string, string> Built-in resolver mappings
     */
    protected array $builtInResolvers = [
        'domain' => DomainResolver::class,
        'subdomain' => SubdomainResolver::class,
        'path' => PathResolver::class,
        'header' => HeaderResolver::class,
    ];

    /**
     * @var array<string, string> Custom resolver mappings
     */
    protected array $customResolvers = [];

    /**
     * Register a custom resolver.
     *
     * @param string $name Resolver name
     * @param string $resolverClass Resolver class that implements TenantResolver
     * @throws InvalidArgumentException If resolver doesn't implement TenantResolver
     */
    public function registerResolver(string $name, string $resolverClass): void
    {
        if (!is_subclass_of($resolverClass, TenantResolver::class)) {
            throw new InvalidArgumentException("Resolver must implement TenantResolver interface");
        }

        $this->customResolvers[$name] = $resolverClass;
    }

    /**
     * Resolve tenant using the default resolver strategy.
     *
     * @param Request $request The HTTP request
     * @return Tenant|null The resolved tenant or null if none found
     */
    public function resolve(Request $request): ?Tenant
    {
        $defaultResolver = config('tenant.default_resolver', 'domain');

        return $this->resolveWith($defaultResolver, $request);
    }

    /**
     * Resolve tenant using a specific resolver strategy.
     *
     * @param string $resolverName The resolver strategy name
     * @param Request $request The HTTP request
     * @return Tenant|null The resolved tenant or null if none found
     * @throws InvalidArgumentException If resolver not found
     */
    public function resolveWith(string $resolverName, Request $request): ?Tenant
    {
        $resolverClass = $this->getResolverClass($resolverName);

        if (!$resolverClass) {
            throw new InvalidArgumentException("Resolver '{$resolverName}' not found");
        }

        $resolver = new $resolverClass();

        return $resolver->resolve($request);
    }

    /**
     * Get resolver class for a given name.
     *
     * @param string $name Resolver name
     * @return string|null Resolver class or null if not found
     */
    protected function getResolverClass(string $name): ?string
    {
        return $this->customResolvers[$name] ?? $this->builtInResolvers[$name] ?? null;
    }

    /**
     * Get all available resolver names.
     *
     * @return array<string> Array of resolver names
     */
    public function getAvailableResolvers(): array
    {
        return array_merge(
            array_keys($this->builtInResolvers),
            array_keys($this->customResolvers)
        );
    }
}