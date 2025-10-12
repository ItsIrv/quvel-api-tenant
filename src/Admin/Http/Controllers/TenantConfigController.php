<?php

declare(strict_types=1);

namespace Quvel\Tenant\Admin\Http\Controllers;

use Illuminate\Http\Request;
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
    public function public(Request $request, TenantContext $tenantContext, TenantPublicConfig $action): TenantConfigResource
    {
        return $this->overrideTenant($tenantContext, $request, $action);
    }

    /**
     * Get protected tenant configuration.
     */
    public function protected(Request $request, TenantContext $tenantContext, TenantProtectedConfig $action): TenantConfigResource
    {
        return $this->overrideTenant($tenantContext, $request, $action);
    }

    /**
     * Get all tenants cache for SSR.
     */
    public function cache(
        TenantsCache $action
    ): AnonymousResourceCollection {
        return $action();
    }

    /**
     * @param TenantContext $tenantContext
     * @param Request $request
     * @param TenantProtectedConfig|TenantPublicConfig $action
     * @return TenantConfigResource
     */
    public function overrideTenant(
        TenantContext $tenantContext,
        Request $request,
        TenantProtectedConfig|TenantPublicConfig $action
    ): TenantConfigResource {
        $tenant = $tenantContext->current();

        if ($tenant === null) {
            throw new NotFoundHttpException('Tenant not found');
        }

        if ($tenant->isInternal() && $request->hasHeader('X-Tenant-ID')) {
            $tenantModel = config('tenant.model');
            $targetTenant = $tenantModel::where('identifier', $request->header('X-Tenant-ID'))
                ->with('parent')
                ->first();

            if ($targetTenant) {
                $tenant = $targetTenant;
            }
        }

        return $action($tenant);
    }
}