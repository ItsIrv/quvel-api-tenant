<?php

declare(strict_types=1);

namespace Quvel\Tenant\Managers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Quvel\Tenant\Models\Tenant;
use Quvel\Tenant\Pipes\BasePipe;

/**
 * Manages configuration handlers for applying tenant config to Laravel.
 */
class ConfigurationPipeManager
{
    /**
     * @var Collection<int, BasePipe>
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
    public function register(BasePipe|string $pipe): static
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
     * @param array<BasePipe|string> $pipes
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
     * @return Collection<int, BasePipe>
     */
    public function getPipes(): Collection
    {
        return $this->pipes;
    }
}