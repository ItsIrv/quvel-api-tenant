<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

/**
 * Contract for tenant context management.
 *
 * Provides access to the current tenant for the active request.
 * Implementations should be bound as scoped services to ensure
 * fresh instances per request in long-running processes.
 */
interface TenantContext
{
    /**
     * Set the current tenant for this request.
     *
     * @param mixed $tenant The tenant model instance
     * @return void
     */
    public function setCurrent($tenant): void;

    /**
     * Get the current tenant.
     *
     * @return mixed The current tenant model instance or null
     */
    public function current();

    /**
     * Check if a tenant is currently set.
     *
     * @return bool True if a tenant is set, false otherwise
     */
    public function hasCurrent(): bool;

    /**
     * Clear the current tenant.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Mark the context as bypassed (no tenant required).
     *
     * @return void
     */
    public function bypass(): void;

    /**
     * Check if the context is bypassed.
     *
     * @return bool True if bypassed, false otherwise
     */
    public function isBypassed(): bool;

    /**
     * Restore the context to non-bypassed state.
     *
     * @return void
     */
    public function clearBypassed(): void;
}
