<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolvers;

use Illuminate\Http\Request;

/**
 * Resolves tenants based on the request domain.
 */
class DomainResolver extends BaseResolver
{
    /**
     * Extract tenant identifier from request domain.
     */
    protected function extractIdentifier(Request $request): ?string
    {
        return $request->getHost();
    }
}