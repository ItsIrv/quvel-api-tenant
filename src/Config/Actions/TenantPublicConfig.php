<?php

declare(strict_types=1);

namespace Quvel\Tenant\Config\Actions;

use Quvel\Tenant\Http\Resources\TenantConfigResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Action to get public tenant configuration.
 * Only returns tenant config if the tenant allows public config API access.
 */
class TenantPublicConfig
{
    /**
     * Execute the action.
     */
    public function __invoke($tenant): TenantConfigResource
    {
        $allowPublicConfig = config('tenant.api.allow_public_config', false);

        if ($allowPublicConfig !== true) {
            throw new NotFoundHttpException('API not enabled for this tenant');
        }

        return new TenantConfigResource($tenant)->setVisibilityLevel('public');
    }
}
