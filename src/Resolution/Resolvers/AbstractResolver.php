<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolution\Resolvers;

use Illuminate\Http\Request;
use Quvel\Tenant\Contracts\TenantResolver;

/**
 * Abstract base class for tenant resolvers.
 *
 * Provides generic tenant lookup logic - subclasses only need to
 * implement getIdentifier() to extract the tenant identifier from the request.
 *
 * For advanced use cases, subclasses can override resolve() entirely.
 */
abstract class AbstractResolver implements TenantResolver
{
    /**
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(protected array $config = [])
    {
    }

    /**
     * Resolve tenant from request using identifier extraction.
     *
     * This method can be overridden for custom resolution logic.
     */
    public function resolve(Request $request): mixed
    {
        $identifier = $this->getIdentifier($request);

        if (!$identifier) {
            return null;
        }

        $tenantModel = config('tenant.model');

        return $tenantModel::findByIdentifier($identifier);
    }

    /**
     * Extract the tenant identifier from the request.
     *
     * Subclasses must implement this method to define how the
     * identifier is extracted (from domain, path, header, etc.).
     */
    abstract public function getIdentifier(Request $request): ?string;
}
