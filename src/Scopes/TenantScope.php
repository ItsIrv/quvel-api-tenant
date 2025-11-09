<?php

declare(strict_types=1);

namespace Quvel\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Quvel\Tenant\Concerns\HandlesTenantModels;
use Quvel\Tenant\Events\TenantScopeApplied;
use Quvel\Tenant\Events\TenantScopeNoTenantFound;
use Quvel\Tenant\Exceptions\NoTenantException;
use Quvel\Tenant\Facades\TenantContext;

/**
 * Global scope that automatically filters queries by tenant_id.
 * Respects bypass mode for admin/system operations.
 *
 * Adds the following macros to query builders:
 * - withoutTenantScope() - Remove tenant filtering from query
 * - forTenant($tenantId) - Filter for specific tenant ID
 * - forAllTenants() - Alias for withoutTenantScope()
 *
 * Bypass behavior:
 * - When TenantContext::isBypassed() returns true, no filtering is applied
 * - When no tenant is set in context, no filtering is applied (allows global data)
 * - Otherwise, queries are filtered to the current tenant's ID
 */
class TenantScope implements Scope
{
    use HandlesTenantModels;

    public function __construct(
        protected string $column = 'tenant_id'
    ) {
    }

    /**
     * Apply the scope to a given Eloquent query.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::isBypassed()) {
            return;
        }

        $tenant = TenantContext::current();

        if (!$tenant) {
            TenantScopeNoTenantFound::dispatch($model::class);

            if (config('tenant.scoping.throw_no_tenant_exception', true)) {
                throw new NoTenantException(
                    sprintf(
                        'No tenant context found for model %s. '
                            . 'Ensure tenant middleware is active or use without_tenant() for admin operations.',
                        $model::class
                    )
                );
            }

            // Return empty results by applying an impossible condition
            $builder->whereRaw('1 = 0');

            return;
        }

        if (!TenantContext::needsTenantIdScope()) {
            TenantScopeApplied::dispatch($model::class, $tenant, 'isolated');

            return;
        }

        TenantScopeApplied::dispatch($model::class, $tenant, $this->column);

        $builder->where($this->column, $tenant->id);
    }

    /**
     * Extend the query builder with tenant-specific methods.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', fn (Builder $builder) => $builder->withoutGlobalScope($this));

        $builder->macro('forTenant', fn (Builder $builder, $tenantId) => $builder->withoutGlobalScope($this)
            ->where($this->column, $tenantId));

        $builder->macro('forAllTenants', fn (Builder $builder) => $builder->withoutGlobalScope($this));
    }
}
