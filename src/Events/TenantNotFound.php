<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when no tenant could be resolved from a request.
 */
class TenantNotFound
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Request $request,
        public string $resolverClass,
        public ?string $cacheKey = null,
    ) {}
}