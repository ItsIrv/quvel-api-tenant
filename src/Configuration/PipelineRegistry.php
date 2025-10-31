<?php

declare(strict_types=1);

namespace Quvel\Tenant\Configuration;

use Illuminate\Support\Collection;
use Quvel\Tenant\Contracts\PipelineRegistry as PipelineRegistryContract;
use Quvel\Tenant\Pipes\BasePipe;

/**
 * Registry for configuration pipes that apply tenant config to Laravel.
 */
class PipelineRegistry implements PipelineRegistryContract
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
     * Get all registered pipes.
     *
     * @return Collection<int, BasePipe>
     */
    public function getPipes(): Collection
    {
        return $this->pipes;
    }

    /**
     * Apply all registered pipes to a tenant.
     *
     * Executes each pipe's handle method with the tenant and config.
     *
     * @param mixed $tenant The tenant model instance
     * @return void
     */
    public function applyPipes($tenant): void
    {
        foreach ($this->pipes as $pipe) {
            $pipe->handle($tenant, config());
        }
    }
}
