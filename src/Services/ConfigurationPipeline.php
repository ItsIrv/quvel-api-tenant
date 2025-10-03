<?php

declare(strict_types=1);

namespace Quvel\Tenant\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Quvel\Tenant\Concerns\ConfigurationPipe;
use Quvel\Tenant\Models\Tenant;

/**
 * Manages configuration handlers for applying tenant config to Laravel.
 */
class ConfigurationPipeline
{
    /**
     * @var Collection<int, ConfigurationPipe>
     */
    protected Collection $pipes;

    public function __construct()
    {
        $this->pipes = collect();
        $this->loadPipesFromConfig();
    }

    /**
     * Load pipes from configuration.
     */
    protected function loadPipesFromConfig(): void
    {
        $pipes = config('tenant.pipes', []);
        $this->registerMany($pipes);
    }

    /**
     * Register a configuration pipe.
     */
    public function register(ConfigurationPipe|string $pipe): static
    {
        if (is_string($pipe)) {
            $pipe = app($pipe);
        }

        $this->pipes->push($pipe);

        return $this;
    }

    /**
     * Register multiple pipes.
     *
     * @param array<ConfigurationPipe|string> $pipes
     */
    public function registerMany(array $pipes): static
    {
        foreach ($pipes as $pipe) {
            $this->register($pipe);
        }

        return $this;
    }

    /**
     * Apply tenant configuration through all pipes.
     */
    public function apply(Tenant $tenant, ConfigRepository $config): void
    {
        foreach ($this->pipes as $pipe) {
            $pipe->handle($tenant, $config);
        }
    }

    /**
     * Get all registered pipes.
     *
     * @return Collection<int, ConfigurationPipe>
     */
    public function getPipes(): Collection
    {
        return $this->pipes;
    }
}