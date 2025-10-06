<?php

declare(strict_types=1);

namespace Quvel\Tenant\Concerns;

use Quvel\Tenant\Facades\TenantContext;
use Quvel\Tenant\Models\Tenant;

/**
 * Base trait for making any class tenant-aware.
 *
 * This trait provides common helper methods for working with tenant context
 * across all domains (broadcasting, mail, events, etc.).
 *
 * Used by domain-specific TenantAware traits to avoid code duplication.
 */
trait TenantAware
{
    /**
     * Get the current tenant from context.
     */
    protected function getCurrentTenant(): ?Tenant
    {
        return TenantContext::current();
    }

    /**
     * Get the current tenant ID.
     */
    protected function getCurrentTenantId(): ?int
    {
        return $this->getCurrentTenant()?->id;
    }

    /**
     * Get the current tenant public ID.
     */
    protected function getCurrentTenantPublicId(): ?string
    {
        return $this->getCurrentTenant()?->public_id;
    }

    /**
     * Get the current tenant name.
     */
    protected function getCurrentTenantName(): ?string
    {
        return $this->getCurrentTenant()?->name;
    }

    /**
     * Check if we're currently in a tenant context.
     */
    protected function hasTenantContext(): bool
    {
        return $this->getCurrentTenant() !== null;
    }

    /**
     * Check if tenant context is bypassed.
     */
    protected function isTenantBypassed(): bool
    {
        return TenantContext::isBypassed();
    }

    /**
     * Check if this operation should be processed for the current tenant.
     * Override this method to implement custom logic.
     */
    protected function shouldProcessForTenant(): bool
    {
        return $this->hasTenantContext() && !$this->isTenantBypassed();
    }

    /**
     * Get tenant-specific data for any purpose.
     */
    protected function getTenantData(): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [];
        }

        return [
            'tenant_id' => $tenant->id,
            'tenant_public_id' => $tenant->public_id,
            'tenant_name' => $tenant->name,
        ];
    }

    /**
     * Get a tenant-specific prefix.
     */
    protected function getTenantPrefix(): string
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return '';
        }

        return "tenant.{$tenant->public_id}.";
    }

    /**
     * Create a tenant-scoped name.
     */
    protected function getTenantScopedName(string $name): string
    {
        $prefix = $this->getTenantPrefix();

        if (empty($prefix)) {
            return $name;
        }

        return $prefix . $name;
    }

    /**
     * Execute callback with specific tenant context.
     */
    protected function withTenant(?Tenant $tenant, callable $callback): mixed
    {
        $original = TenantContext::current();

        try {
            TenantContext::setCurrent($tenant);
            return $callback();
        } finally {
            TenantContext::setCurrent($original);
        }
    }

    /**
     * Execute callback without tenant context (bypassed).
     */
    protected function withoutTenant(callable $callback): mixed
    {
        $wasBypassed = TenantContext::isBypassed();

        try {
            TenantContext::bypass();
            return $callback();
        } finally {
            if (!$wasBypassed) {
                TenantContext::clearBypassed();
            }
        }
    }
}