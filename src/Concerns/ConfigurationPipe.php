<?php

declare(strict_types=1);

namespace Quvel\Tenant\Concerns;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Quvel\Tenant\Models\Tenant;

/**
 * Configuration pipe interface for applying tenant config to Laravel.
 */
interface ConfigurationPipe
{
    /**
     * Apply tenant configuration to Laravel's runtime config.
     *
     * @param Tenant $tenant The tenant context
     * @param ConfigRepository $config Laravel config repository
     */
    public function handle(Tenant $tenant, ConfigRepository $config): void;
}