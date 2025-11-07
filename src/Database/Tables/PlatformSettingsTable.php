<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database\Tables;

use Quvel\Tenant\Database\BaseTenantTable;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Tenant configuration for the platform_settings table.
 */
class PlatformSettingsTable extends BaseTenantTable
{
    public function tableName(): string
    {
        return 'platform_settings';
    }

    public function getConfig(): TenantTableConfig
    {
        return $this->config()
            ->after('id')
            ->cascadeDelete()
            ->dropUnique(['platform'])
            ->tenantUnique(['platform'])
            ->dropIndex(['platform'])
            ->tenantIndex(['platform']);
    }
}
