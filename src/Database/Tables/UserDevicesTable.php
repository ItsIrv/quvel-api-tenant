<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database\Tables;

use Quvel\Tenant\Database\BaseTenantTable;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Tenant configuration for the user_devices table.
 */
class UserDevicesTable extends BaseTenantTable
{
    public function tableName(): string
    {
        return 'user_devices';
    }

    public function getConfig(): TenantTableConfig
    {
        return $this->config()
            ->after('id')
            ->cascadeDelete()
            ->dropUnique(['device_id'])
            ->tenantUnique(['device_id'])
            ->dropIndex(['user_id', 'is_active'])
            ->tenantIndex(['user_id', 'is_active'])
            ->dropIndex(['platform', 'is_active'])
            ->tenantIndex(['platform', 'is_active'])
            ->dropIndex(['last_seen_at'])
            ->tenantIndex(['last_seen_at'])
            ->dropForeignKey('user_devices_user_id_foreign')
            ->recreateForeignKey('user_id', 'id', 'users', 'cascade');
    }
}
