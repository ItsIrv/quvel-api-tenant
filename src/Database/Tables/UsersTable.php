<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database\Tables;

use Quvel\Tenant\Database\BaseTenantTable;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Tenant configuration for the users table.
 */
class UsersTable extends BaseTenantTable
{
    public function tableName(): string
    {
        return 'users';
    }

    public function getConfig(): TenantTableConfig
    {
        return $this->config()
            ->after('id')
            ->cascadeDelete()
            ->dropUnique(['email'])
            ->tenantUnique(['email']);
    }
}
