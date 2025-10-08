<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

use Illuminate\Support\Collection;
use Quvel\Tenant\Pipes\BasePipe;

/**
 * Contract for managing configuration pipes that apply tenant config to Laravel.
 */
interface PipelineRegistry
{
    /**
     * Register a configuration pipe.
     */
    public function register(BasePipe|string $pipe): static;

    /**
     * Register multiple pipes.
     *
     * @param array<BasePipe|string> $pipes
     */
    public function registerMany(array $pipes): static;

    /**
     * Get all registered pipes.
     *
     * @return Collection<int, BasePipe>
     */
    public function getPipes(): Collection;
}
