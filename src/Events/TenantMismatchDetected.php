<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a cross-tenant operation is detected and blocked.
 */
class TenantMismatchDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $modelClass,
        public ?int $modelTenantId,
        public ?int $currentTenantId,
        public string $operation
    ) {
    }
}
