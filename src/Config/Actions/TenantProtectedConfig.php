<?php

declare(strict_types=1);

namespace Quvel\Tenant\Config\Actions;

use Quvel\Tenant\Http\Resources\TenantConfigResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Action to get protected tenant configuration.
 * Used by SSR for internal network communication.
 */
class TenantProtectedConfig
{
    /**
     * Execute the action.
     */
    public function __invoke($tenant): TenantConfigResource
    {
        $allowProtectedConfig = config('tenant.api.allow_protected_config', false);

        if ($allowProtectedConfig !== true) {
            throw new NotFoundHttpException('API not enabled for this tenant');
        }

        $tenantConfigResource = new TenantConfigResource($tenant);

        return $tenantConfigResource->setVisibilityLevel('protected');
    }
}
