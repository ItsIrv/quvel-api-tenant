<?php

declare(strict_types=1);

namespace Quvel\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Exceptions\NoTenantException;
use Quvel\Tenant\Events\TenantScopeApplied;
use Quvel\Tenant\Events\TenantScopeNoTenantFound;

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
 * - Otherwise, queries are filtered to current tenant's ID
 */
class TenantScope implements Scope
{
    public function __construct(
        protected string $column = 'tenant_id'
    ) {
    }

    /**
     * Apply the scope to a given Eloquent query.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->isBypassed()) {
            return;
        }

        $tenant = $tenantContext->current();

        if (!$tenant) {
            TenantScopeNoTenantFound::dispatch(get_class($model));

            if (config('tenant.scoping.throw_no_tenant_exception', true)) {
                throw new NoTenantException(
                    sprintf(
                        'No tenant context found for model %s. Ensure tenant middleware is active or use without_tenant() for admin operations.',
                        get_class($model)
                    )
                );
            }

            // Return empty results by applying impossible condition
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($this->tenantUsesIsolatedDatabase($tenant)) {
            TenantScopeApplied::dispatch(get_class($model), $tenant, 'database_isolation');

            return;
        }

        TenantScopeApplied::dispatch(get_class($model), $tenant, $this->column);

        $builder->where($this->column, $tenant->id);
    }

    /**
     * Extend the query builder with tenant-specific methods.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forTenant', function (Builder $builder, $tenantId) {
            return $builder->withoutGlobalScope($this)
                ->where($this->column, $tenantId);
        });

        $builder->macro('forAllTenants', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Check if tenant uses isolated database (separate database/server).
     *
     * When true, tenant_id scoping is not needed since database isolation
     * provides the tenant boundary.
     */
    protected function tenantUsesIsolatedDatabase($tenant): bool
    {
        $baseConnection = $tenant->getConfig('database.default') ?? 'mysql';

        // Isolated if tenant has custom host OR custom database name
        return $tenant->hasConfig("database.connections.$baseConnection.host") ||
               $tenant->hasConfig("database.connections.$baseConnection.database");
    }
}