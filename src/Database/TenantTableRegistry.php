<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database;

use InvalidArgumentException;

/**
 * Registry for tenant-aware tables.
 *
 * Manages which tables should have tenant_id columns and their configuration.
 */
class TenantTableRegistry
{
    /**
     * Registered tenant-aware tables.
     *
     * @var array<string, TenantTableConfig>
     */
    protected array $tables = [];

    /**
     * Register a table to be made tenant-aware.
     *
     * @param string $tableName The name of the table
     * @param bool|array<string, mixed>|TenantTableConfig|string $config Configuration for the table
     * @return void
     */
    public function register(string $tableName, bool|array|TenantTableConfig|string $config = true): void
    {
        if ($config instanceof TenantTableConfig) {
            $this->tables[$tableName] = $config;

            return;
        }

        if ($config === true) {
            $this->tables[$tableName] = TenantTableConfig::default();

            return;
        }

        if (is_array($config)) {
            $this->tables[$tableName] = TenantTableConfig::fromArray($config);

            return;
        }

        if (is_string($config) && class_exists($config)) {
            $instance = new $config();

            if (method_exists($instance, 'getConfig')) {
                $this->tables[$tableName] = $instance->getConfig();

                return;
            }

            throw new InvalidArgumentException(
                "Class $config must have a getConfig() method that returns TenantTableConfig"
            );
        }

        throw new InvalidArgumentException(
            "Invalid configuration type for table $tableName"
        );
    }

    /**
     * Register multiple tables at once.
     *
     * @param array<string, mixed> $tables Array of table configurations
     * @return void
     */
    public function registerMany(array $tables): void
    {
        foreach ($tables as $tableName => $config) {
            $this->register($tableName, $config);
        }
    }

    /**
     * Load tables from configuration.
     *
     * @return void
     */
    public function loadFromConfig(): void
    {
        $tables = config('tenant.tables', []);

        $this->registerMany($tables);
    }

    /**
     * Get all registered tables.
     *
     * @return array<string, TenantTableConfig>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * Get configuration for a specific table.
     *
     * @param string $tableName
     * @return TenantTableConfig|null
     */
    public function getTable(string $tableName): ?TenantTableConfig
    {
        return $this->tables[$tableName] ?? null;
    }

    /**
     * Check if a table is registered.
     *
     * @param string $tableName
     * @return bool
     */
    public function hasTable(string $tableName): bool
    {
        return isset($this->tables[$tableName]);
    }

    /**
     * Get the count of registered tables.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->tables);
    }
}