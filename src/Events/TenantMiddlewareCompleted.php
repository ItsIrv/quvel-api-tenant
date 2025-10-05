<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Quvel\Tenant\Models\Tenant;

/**
 * Dispatched when tenant middleware has completed processing.
 */
class TenantMiddlewareCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Request $request,
    ) {}
}