<?php

declare(strict_types=1);

namespace Quvel\Tenant\Concerns;

use Quvel\Tenant\Events\TenantMismatchDetected;
use Quvel\Tenant\Exceptions\TenantMismatchException;
use Quvel\Tenant\Facades\TenantContext;

/**
 * Shared logic for tenant model scoping and validation.
 */
trait HandlesTenantModels
{
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

        if (!TenantContext::needsTenantIdScope()) {
            return;
        }

        $currentTenantId = tenant_id();
        $modelTenantId = $model->tenant_id;

        if ($modelTenantId !== null && $modelTenantId !== $currentTenantId) {
            TenantMismatchDetected::dispatch(
                $model::class,
                $modelTenantId,
                $currentTenantId,
                $operation
            );

            throw new TenantMismatchException(
                sprintf(
                    'Cross-tenant operation blocked: %s (tenant_id: %s) cannot be modified from tenant %s context',
                    $model::class,
                    $modelTenantId,
                    $currentTenantId ?? 0
                )
            );
        }
    }
}
