<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when tenant middleware has completed processing.
 */
class TenantMiddlewareCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public $tenant,
        public Request $request,
    ) {}
}