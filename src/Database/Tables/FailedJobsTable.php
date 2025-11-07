<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database\Tables;

use Quvel\Tenant\Database\BaseTenantTable;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Tenant configuration for the failed_jobs table.
 */
class FailedJobsTable extends BaseTenantTable
{
    public function tableName(): string
    {
        return 'failed_jobs';
    }

    public function getConfig(): TenantTableConfig
    {
        return $this->config()
            ->after('id')
            ->cascadeDelete()
            ->dropUnique(['uuid'])
            ->tenantUnique(['uuid']);
    }
}
