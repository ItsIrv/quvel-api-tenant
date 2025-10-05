<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Quvel\Tenant\Models\Tenant;

/**
 * Dispatched when a tenant context has been set.
 */
class TenantContextSet
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
    ) {}
}