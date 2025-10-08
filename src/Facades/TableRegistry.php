<?php

declare(strict_types=1);

namespace Quvel\Tenant\Facades;

use Illuminate\Support\Facades\Facade;
use Quvel\Tenant\Database\TenantTableConfig;

/**
 * @method static void register(string $tableName, bool|array|TenantTableConfig|string $config = true)
 * @method static void registerMany(array $tables)
 * @method static array getTables()
 * @method static TenantTableConfig|null getTable(string $tableName)
 * @method static bool hasTable(string $tableName)
 * @method static int count()
 * @method static array processTables(?array $tableNames = null)
 * @method static array removeTenantSupport(?array $tableNames = null)
 *
 * @see \Quvel\Tenant\Contracts\TableRegistry
 */
class TableRegistry extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Quvel\Tenant\Contracts\TableRegistry::class;
    }
}
