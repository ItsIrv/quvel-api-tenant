<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database\Tables;

use Quvel\Tenant\Database\BaseTenantTable;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Tenant configuration for Laravel Telescope's telescope_monitoring table.
 */
class TelescopeMonitoringTable extends BaseTenantTable
{
    public function tableName(): string
    {
        return 'telescope_monitoring';
    }

    public function getConfig(): TenantTableConfig
    {
        return $this->autoDetect(['after' => 'tag'])->nullable();
    }
}
