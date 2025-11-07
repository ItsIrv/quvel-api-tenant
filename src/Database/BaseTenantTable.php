<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * Base class for class-based tenant table configurations.
 *
 * Extend this class to create custom table configurations that can be
 * referenced in the tenant config file.
 *
 * Example usage with auto-detection:
 * ```php
 * class UserDevicesTable extends BaseTenantTable
 * {
 *     public function tableName(): string
 *     {
 *         return 'user_devices';
 *     }
 *
 *     public function getConfig(): TenantTableConfig
 *     {
 *         return $this->autoDetect();
 *     }
 * }
 * ```
 *
 * Example usage with fluent DSL:
 * ```php
 * class UsersTable extends BaseTenantTable
 * {
 *     public function tableName(): string
 *     {
 *         return 'users';
 *     }
 *
 *     public function getConfig(): TenantTableConfig
 *     {
 *         return $this->config()
 *             ->after('id')
 *             ->cascadeDelete()
 *             ->dropUnique(['email'])
 *             ->tenantUnique(['email']);
 *     }
 * }
 * ```
 *
 * Then in config/tenant.php:
 * ```php
 * 'tables' => [
 *     'users' => \App\Database\Tables\UsersTable::class,
 *     'user_devices' => \App\Database\Tables\UserDevicesTable::class,
 * ]
 * ```
 */
abstract class BaseTenantTable
{
    /**
     * Get the table name this configuration applies to.
     */
    abstract public function tableName(): string;

    /**
     * Get the tenant configuration for this table.
     */
    abstract public function getConfig(): TenantTableConfig;

    /**
     * Start building configuration with fluent API.
     */
    protected function config(): TenantTableConfig
    {
        return TenantTableConfig::for($this->tableName());
    }

    /**
     * Auto-detect schema and build configuration.
     */
    protected function autoDetect(array $overrides = []): TenantTableConfig
    {
        return TenantTableConfig::fromTable($this->tableName(), $overrides);
    }

    /**
     * Hook executed before the tenant_id column is added.
     */
    public function beforeAddTenant(Blueprint $table): void
    {
    }

    /**
     * Hook executed after the tenant_id column is added.
     */
    public function afterAddTenant(Blueprint $table): void
    {
    }

    /**
     * Hook executed before the tenant_id column is removed.
     */
    public function beforeRemoveTenant(Blueprint $table): void
    {
    }

    /**
     * Hook executed after the tenant_id column is removed.
     */
    public function afterRemoveTenant(Blueprint $table): void
    {
    }
}
