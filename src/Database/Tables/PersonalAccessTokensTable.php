<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database\Tables;

use Quvel\Tenant\Database\BaseTenantTable;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Tenant configuration for Laravel Sanctum personal_access_tokens table.
 */
class PersonalAccessTokensTable extends BaseTenantTable
{
    public function tableName(): string
    {
        return 'personal_access_tokens';
    }

    public function getConfig(): TenantTableConfig
    {
        return $this->config()
            ->after('id')
            ->cascadeDelete()
            ->dropUnique(['token'])
            ->tenantUnique(['token'])
            ->dropIndex(['expires_at'])
            ->tenantIndex(['expires_at'])
            ->dropIndex(['tokenable_type', 'tokenable_id'])
            ->tenantIndex(['tokenable_type', 'tokenable_id']);
    }
}
