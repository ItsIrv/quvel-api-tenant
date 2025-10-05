<?php

declare(strict_types=1);

namespace Quvel\Tenant\Context;

use Quvel\Tenant\Events\TenantContextSet;
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
    protected bool $bypassed = false;

    /**
     * Set the current tenant for this request.
     */
    public function setCurrent(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        if ($tenant) {
            TenantContextSet::dispatch($tenant);
        }
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
        $this->bypassed = false;
    }

    /**
     * Mark the context as bypassed (no tenant required).
     */
    public function bypass(): void
    {
        $this->bypassed = true;
    }

    /**
     * Check if the context is bypassed.
     */
    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Restore the context to non-bypassed state.
     */
    public function clearBypassed(): void
    {
        $this->bypassed = false;
    }
}