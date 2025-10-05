<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Quvel\Tenant\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a tenant is successfully resolved from a request.
 */
class TenantResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $resolverClass,
        public ?string $cacheKey,
    ) {}
}