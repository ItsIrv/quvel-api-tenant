<?php

declare(strict_types=1);

namespace Quvel\Tenant\Contracts;

use Quvel\Tenant\Database\TenantTableConfig;

/**
 * Contract for managing tenant-aware database tables.
 */
interface TableRegistry
{
    /**
     * Register a table to be made tenant-aware.
     *
     * @param string $tableName The name of the table
     * @param bool|array<string, mixed>|TenantTableConfig|string $config Configuration for the table
     */
    public function register(string $tableName, bool|array|TenantTableConfig|string $config = true): void;

    /**
     * Register multiple tables at once.
     *
     * @param array<string, mixed> $tables Array of table configurations
     */
    public function registerMany(array $tables): void;

    /**
     * Get all registered tables.
     *
     * @return array<string, TenantTableConfig>
     */
    public function getTables(): array;

    /**
     * Get configuration for a specific table.
     */
    public function getTable(string $tableName): ?TenantTableConfig;

    /**
     * Check if a table is registered.
     */
    public function hasTable(string $tableName): bool;

    /**
     * Get the count of registered tables.
     */
    public function count(): int;

    /**
     * Process configured tables to add tenant functionality.
     *
     * @param array|null $tableNames Optional array of specific table names to process.
     * @return array Results array with table names as keys and status as values
     */
    public function processTables(?array $tableNames = null): array;

    /**
     * Remove tenant functionality from configured tables.
     *
     * @param array|null $tableNames Optional array of specific table names to process.
     * @return array Results array with table names as keys and status as values
     */
    public function removeTenantSupport(?array $tableNames = null): array;
}
