<?php

declare(strict_types=1);

namespace Quvel\Tenant\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Quvel\Tenant\Pipes\BasePipe;

/**
 * @method static static register(BasePipe|string $pipe)
 * @method static static registerMany(array $pipes)
 * @method static Collection getPipes()
 *
 * @see \Quvel\Tenant\Contracts\PipelineRegistry
 */
class PipelineRegistry extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Quvel\Tenant\Contracts\PipelineRegistry::class;
    }
}
