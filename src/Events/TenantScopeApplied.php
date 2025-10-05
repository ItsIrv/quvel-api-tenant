<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Quvel\Tenant\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when tenant scope is applied to a query.
 */
class TenantScopeApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $modelClass,
        public Tenant $tenant,
        public string $column = 'tenant_id'
    ) {}
}