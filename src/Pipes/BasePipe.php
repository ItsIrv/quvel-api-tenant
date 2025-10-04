<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Quvel\Tenant\Concerns\ConfigurationPipe;
use Quvel\Tenant\Models\Tenant;

/**
 * Base configuration pipe with helper methods.
 */
abstract class BasePipe implements ConfigurationPipe
{
    protected Tenant $tenant;
    protected ConfigRepository $config;

    /**
     * Set the context for this pipe execution.
     */
    protected function setContext(Tenant $tenant, ConfigRepository $config): void
    {
        $this->tenant = $tenant;
        $this->config = $config;
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
     * Usage: $this->setMany(['app_name' => 'app.name', 'app_url' => 'app.url'])
     */
    protected function setMany(array $mappings): void
    {
        foreach ($mappings as $tenantKey => $laravelKey) {
            $this->setIfExists($tenantKey, $laravelKey);
        }
    }
}