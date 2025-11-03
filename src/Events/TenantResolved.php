<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a tenant is successfully resolved from a request.
 */
class TenantResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public $tenant,
        public string $resolverClass,
        public ?string $cacheKey,
    ) {
    }
}
