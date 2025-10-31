<?php

declare(strict_types=1);

namespace Quvel\Tenant\Facades;

use Illuminate\Support\Facades\Facade;
use Quvel\Tenant\Models\Tenant;

/**
 * @method static Tenant|null current()
 * @method static void setCurrent(?Tenant $tenant)
 * @method static bool hasCurrent()
 * @method static void clear()
 * @method static bool isBypassed()
 * @method static void bypass()
 * @method static void clearBypassed()
 *
 * @see \Quvel\Tenant\Contracts\TenantContext
 * @see \Quvel\Tenant\Context\TenantContext
 */
class TenantContext extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Quvel\Tenant\Contracts\TenantContext::class;
    }
}