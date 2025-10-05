<?php

declare(strict_types=1);

namespace Quvel\Tenant\Traits;

use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Events\TenantMismatchDetected;
use Quvel\Tenant\Exceptions\TenantMismatchException;
use Quvel\Tenant\Models\Tenant;

/**
 * Shared logic for tenant model scoping and validation.
 */
trait HandlesTenantModels
{
    /**
     * Check if tenant uses isolated database and should skip tenant_id logic.
     */
    protected function tenantUsesIsolatedDatabase(Tenant $tenant): bool
    {
        $baseConnection = $tenant->getConfig('database.default') ?? 'mysql';
        $hasIsolatedDatabase = $tenant->hasConfig("database.connections.$baseConnection.host") ||
                               $tenant->hasConfig("database.connections.$baseConnection.database");

        if (!$hasIsolatedDatabase) {
            return false;
        }

        return config('tenant.scoping.skip_tenant_id_in_isolated_databases', false);
    }

    /**
     * Validate that model belongs to current tenant context.
     *
     * @throws TenantMismatchException
     */
    protected function validateTenantMatch($model, string $operation = 'modify'): void
    {
        if (tenant_bypassed()) {
            return;
        }

        $tenant = app(TenantContext::class)->current();

        if ($tenant && $this->tenantUsesIsolatedDatabase($tenant)) {
            return;
        }

        $currentTenantId = tenant_id();
        $modelTenantId = $model->tenant_id;

        if ($modelTenantId !== null && $modelTenantId !== $currentTenantId) {
            TenantMismatchDetected::dispatch(
                get_class($model),
                $modelTenantId,
                $currentTenantId,
                $operation
            );

            throw new TenantMismatchException(
                sprintf(
                    'Cross-tenant operation blocked: %s (tenant_id: %s) cannot be modified from tenant %s context',
                    get_class($model),
                    $modelTenantId,
                    $currentTenantId
                )
            );
        }
    }
}