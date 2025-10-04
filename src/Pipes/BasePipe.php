<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Quvel\Tenant\Contracts\ConfigurationPipe;
use Quvel\Tenant\Models\Tenant;

/**
 * Base configuration pipe with helper methods.
 */
abstract class BasePipe implements ConfigurationPipe
{
    protected Tenant $tenant;
    protected ConfigRepository $config;

    /**
     * Apply the configuration changes. Override this in child classes.
     */
    abstract public function apply(): void;

    /**
     * Handle method wrapper that sets context automatically.
     */
    public function handle(Tenant $tenant, ConfigRepository $config): void
    {
        $this->tenant = $tenant;
        $this->config = $config;

        $this->apply();
    }

    /**
     * Set Laravel config if tenant config value exists.
     *
     * Usage: $this->setIfExists('app_name', 'app.name')
     */
    protected function setIfExists(string $tenantKey, string $laravelKey): void
    {
        if (!$this->tenant->hasConfig($tenantKey)) {
            return;
        }

        $value = $this->tenant->getConfig($tenantKey);
        $this->config->set($laravelKey, $value);
    }

    /**
     * Set multiple Laravel config values from tenant config.
     *
     * Usage:
     * - Direct mapping: $this->setMany(['app.name', 'cache.prefix'])
     * - Custom mapping: $this->setMany(['pusher_key' => 'broadcasting.connections.pusher.key'])
     * - Mixed: $this->setMany(['app.name', 'pusher_key' => 'broadcasting.connections.pusher.key'])
     */
    protected function setMany(array $mappings): void
    {
        foreach ($mappings as $tenantKey => $laravelKey) {
            if (is_int($tenantKey)) {
                $this->setIfExists($laravelKey, $laravelKey);
            } else {
                $this->setIfExists($tenantKey, $laravelKey);
            }
        }
    }
}