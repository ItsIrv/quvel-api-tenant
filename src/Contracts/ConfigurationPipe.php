<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

/**
 * Configuration pipe interface for applying tenant config to Laravel.
 */
interface ConfigurationPipe
{
    /**
     * Apply tenant configuration changes.
     */
    public function apply(): void;
}