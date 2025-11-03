<?php

declare(strict_types=1);

namespace Quvel\Tenant\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Quvel\Tenant\Models\Tenant;

/**
 * Provides tenant relationship and helper methods.
 * Use this on models that belong to a tenant.
 *
 * @mixin Model
 *
 * @property int|null $tenant_id The tenant ID this model belongs to
 * @property-read mixed $tenant The tenant relationship
 *
 * @method static Builder forCurrentTenant() Scope to current tenant
 * @method static Builder forTenant(int $tenantId) Scope to specific tenant
 */
trait HasTenant
{
    use TenantAware;

    /**
     * Get the tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(tenant_class(), 'tenant_id');
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
