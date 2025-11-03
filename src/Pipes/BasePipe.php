<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Quvel\Tenant\Contracts\ConfigurationPipe;

/**
 * Base configuration pipe with helper methods.
 */
abstract class BasePipe implements ConfigurationPipe
{
    /**
     * Configurator closures for customizing pipe behavior.
     */
    protected static array $configurators = [];

    protected $tenant;
    protected ConfigRepository $config;

    /**
     * Apply the configuration changes. Override this in child classes.
     */
    abstract public function apply(): void;

    /**
     * Handle is a method wrapper that sets context automatically.
     */
    public function handle($tenant, ConfigRepository $config): void
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

    /**
     * Register a configurator closure.
     */
    public static function registerConfigurator(string $key, Closure $callback): void
    {
        static::$configurators[$key] = $callback;
    }

    /**
     * Apply a configurator or return default value.
     */
    protected function applyConfigurator(string $key, string $defaultValue): string
    {
        if (isset(static::$configurators[$key])) {
            return static::$configurators[$key]($this);
        }

        return $defaultValue;
    }

    /**
     * Check if a configurator is registered.
     */
    protected function hasConfigurator(string $key): bool
    {
        return isset(static::$configurators[$key]);
    }
}
