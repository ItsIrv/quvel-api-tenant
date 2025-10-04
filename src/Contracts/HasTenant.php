<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Models\Tenant;

/**
 * Provides tenant relationship and helper methods.
 * Use this on models that belong to a tenant.
 *
 * @mixin Model
 *
 * @property int|null $tenant_id The tenant ID this model belongs to
 * @property-read Tenant|null $tenant The tenant relationship
 *
 * @method static Builder forCurrentTenant() Scope to current tenant
 * @method static Builder forTenant(int $tenantId) Scope to specific tenant
 */
trait HasTenant
{
    /**
     * Get the tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        $tenantModel = config('tenant.model', Tenant::class);

        return $this->belongsTo($tenantModel, 'tenant_id');
    }

    /**
     * Get the current tenant from context.
     */
    public function getCurrentTenant(): ?Tenant
    {
        return app(TenantContext::class)->current();
    }

    /**
     * Get the current tenant ID from context.
     */
    public function getCurrentTenantId(): ?int
    {
        return $this->getCurrentTenant()?->id;
    }

    /**
     * Check if this model belongs to the current tenant.
     */
    public function belongsToCurrentTenant(): bool
    {
        $currentTenantId = $this->getCurrentTenantId();

        return $currentTenantId && $this->tenant_id === $currentTenantId;
    }

    /**
     * Set the tenant_id to the current tenant.
     */
    public function assignToCurrentTenant(): static
    {
        $this->tenant_id = $this->getCurrentTenantId();

        return $this;
    }

    /**
     * Scope query to current tenant.
     */
    public function scopeForCurrentTenant($query)
    {
        $tenantId = $this->getCurrentTenantId();

        return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
    }

    /**
     * Scope query to specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}