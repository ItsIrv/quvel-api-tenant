<?php

declare(strict_types=1);

namespace Quvel\Tenant\Context;

use Quvel\Tenant\Models\Tenant;

/**
 * Octane-safe per-request tenant context.
 *
 * Holds the current tenant for the active request. Bound as a non-singleton
 * to Laravel's container, ensuring fresh instances per request and automatic
 * cleanup in long-running processes like Octane/Swoole.
 *
 * Usage:
 * - Inject via dependency injection in controllers/services
 * - Access current tenant: $context->current()
 * - Check if tenant is set: $context->hasCurrent()
 */
class TenantContext
{
    protected ?Tenant $tenant = null;

    /**
     * Set the current tenant for this request.
     */
    public function setCurrent(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the current tenant.
     */
    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Check if a tenant is currently set.
     */
    public function hasCurrent(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Clear the current tenant.
     */
    public function clear(): void
    {
        $this->tenant = null;
    }
}