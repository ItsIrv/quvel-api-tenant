<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolution;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use Quvel\Tenant\Contracts\TenantResolver;
use Quvel\Tenant\Resolution\Resolvers\DomainResolver;

/**
 * Manager for tenant resolver drivers.
 *
 * Allows users to switch between different resolver strategies
 * (domain, subdomain, path, etc.) via configuration.
 */
class ResolverManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        $resolvers = $this->config->get('tenant.resolver.resolvers');

        if (empty($resolvers) || !is_array($resolvers)) {
            throw new InvalidArgumentException(
                'No tenant resolvers configured. Add at least one resolver to tenant.resolver.resolvers'
            );
        }

        $firstResolver = $resolvers[0];

        if (!is_array($firstResolver) || $firstResolver === []) {
            throw new InvalidArgumentException(
                'Invalid resolver configuration. Expected format: [["domain" => []]]'
            );
        }

        return array_key_first($firstResolver);
    }

    /**
     * Create the domain resolver driver.
     */
    public function createDomainDriver(?array $config = null): TenantResolver
    {
        $config ??= $this->config->get('tenant.resolver.config', []);

        return new DomainResolver($config);
    }

    /**
     * Make a resolver instance with custom config.
     *
     * @param string $driver The resolver driver name
     * @param array $config Custom configuration for this resolver
     * @throws InvalidArgumentException
     */
    public function makeResolver(string $driver, array $config = []): TenantResolver
    {
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        $resolverClass = $this->config->get('tenant.resolver.drivers.' . $driver);

        if ($resolverClass && class_exists($resolverClass)) {
            if (!is_subclass_of($resolverClass, TenantResolver::class)) {
                throw new InvalidArgumentException(
                    sprintf('Resolver class %s must implement TenantResolver interface', $resolverClass)
                );
            }

            return new $resolverClass($config);
        }

        throw new InvalidArgumentException(sprintf('Driver [%s] not supported.', $driver));
    }

    /**
     * Create a custom resolver driver from configuration.
     */
    protected function createDriver($driver): TenantResolver
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        $resolverClass = $this->config->get('tenant.resolver.drivers.' . $driver);

        if ($resolverClass && class_exists($resolverClass)) {
            $config = $this->config->get('tenant.resolver.config', []);

            if (!is_subclass_of($resolverClass, TenantResolver::class)) {
                throw new InvalidArgumentException(
                    sprintf('Resolver class %s must implement TenantResolver interface', $resolverClass)
                );
            }

            return new $resolverClass($config);
        }

        throw new InvalidArgumentException(sprintf('Driver [%s] not supported.', $driver));
    }
}
