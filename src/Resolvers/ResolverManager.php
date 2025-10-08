<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use Quvel\Tenant\Contracts\TenantResolver;

/**
 * Manager for tenant resolver drivers.
 *
 * Allows users to switch between different resolver strategies
 * (domain, path, etc.) via configuration.
 */
class ResolverManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('tenant.resolver.driver', 'domain');
    }

    /**
     * Create the domain resolver driver.
     */
    public function createDomainDriver(): TenantResolver
    {
        $config = $this->config->get('tenant.resolver.config', []);

        return new DomainResolver($config);
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

        $resolverClass = $this->config->get("tenant.resolver.drivers.$driver");

        if ($resolverClass && class_exists($resolverClass)) {
            $config = $this->config->get('tenant.resolver.config', []);

            if (!is_subclass_of($resolverClass, TenantResolver::class)) {
                throw new InvalidArgumentException(
                    "Resolver class $resolverClass must implement TenantResolver interface"
                );
            }

            return new $resolverClass($config);
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }
}
