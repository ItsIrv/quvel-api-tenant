<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Quvel\Tenant\Actions\TenantProtectedConfig;
use Quvel\Tenant\Actions\TenantPublicConfig;
use Quvel\Tenant\Actions\TenantsCache;
use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Http\Resources\TenantConfigResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for tenant configuration endpoints.
 */
class TenantConfigController
{
    /**
     * Get public tenant configuration.
     */
    public function public(TenantContext $tenantContext, TenantPublicConfig $action): TenantConfigResource
    {
        $tenant = $tenantContext->current();

        if ($tenant === null) {
            throw new NotFoundHttpException('Tenant not found');
        }

        return $action($tenant);
    }

    /**
     * Get protected tenant configuration.
     */
    public function protected(TenantContext $tenantContext, TenantProtectedConfig $action): TenantConfigResource
    {
        $tenant = $tenantContext->current();

        if ($tenant === null) {
            throw new NotFoundHttpException('Tenant not found');
        }

        return $action($tenant);
    }

    /**
     * Get all tenants cache for SSR.
     */
    public function cache(
        TenantsCache $action
    ): AnonymousResourceCollection {
        return $action();
    }
}