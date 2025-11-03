<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when no tenant is found in context for a scoped model.
 */
class TenantScopeNoTenantFound
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $modelClass
    ) {
    }
}
