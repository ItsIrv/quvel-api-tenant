<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database\Tables;

use Quvel\Tenant\Database\BaseTenantTable;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Tenant configuration for Laravel Telescope's telescope_entries_tags table.
 */
class TelescopeEntriesTagsTable extends BaseTenantTable
{
    public function tableName(): string
    {
        return 'telescope_entries_tags';
    }

    public function getConfig(): TenantTableConfig
    {
        // Use auto-detection since Telescope's schema might vary
        return $this->autoDetect();
    }
}
