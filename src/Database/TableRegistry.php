<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database;

use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Quvel\Tenant\Contracts\TableRegistry as TableRegistryContract;

/**
 * Registry for managing tenant-aware database tables.
 *
 * This registry provides a simple way to add tenant_id columns and constraints
 * to existing database tables based on configuration. It automatically detects
 * which tables need processing and skips those that already have tenant support.
 */
class TableRegistry implements TableRegistryContract
{
    /**
     * Registered tenant-aware tables.
     *
     * @var array<string, TenantTableConfig>
     */
    protected array $tables = [];

    /**
     * Process configured tables to add tenant functionality.
     *
     * Adds tenant_id columns and constraints to tables that need them.
     * Automatically skips tables that already have tenant_id columns.
     *
     * @param array|null $tableNames Optional array of specific table names to process.
     *                              If null, processes all configured tables.
     *
     * @return array Results array with table names as keys and status as values:
     *               - 'Processed': Table was successfully updated
     *               - 'skipped_missing': Table doesn't exist in database
     *               - 'skipped_exists': Table already has tenant_id column
     *               - 'error': Processing failed with error message
     *
     * @example
     * // Process all configured tables
     * $results = $registry->processTables();
     *
     * // Process only specific tables
     * $results = $registry->processTables(['users', 'posts']);
     */
    public function processTables(?array $tableNames = null): array
    {
        $this->loadFromConfig();
        $configuredTables = $this->tables;

        if ($tableNames !== null) {
            $configuredTables = array_intersect_key(
                $configuredTables,
                array_flip($tableNames)
            );
        }

        $results = [];

        foreach ($configuredTables as $tableName => $config) {
            $results[$tableName] = $this->processTable($tableName, $config);
        }

        return $results;
    }

    /**
     * Remove tenant functionality from configured tables.
     *
     * Removes tenant_id columns and constraints from tables that have them.
     * Automatically skips tables that don't have tenant_id columns.
     *
     * @param array|null $tableNames Optional array of specific table names to process.
     *                              If null, processes all configured tables.
     *
     * @return array Results array with table names as keys and status as values:
     *               - 'Processed': Table was successfully updated
     *               - 'skipped_missing': Table doesn't exist in database
     *               - 'skipped_no_tenant': Table doesn't have tenant_id column
     *               - 'error': Processing failed with error message
     *
     * @example
     * // Remove tenant support from all configured tables
     * $results = $registry->removeTenantSupport();
     *
     * // Remove tenant support from specific tables only
     * $results = $registry->removeTenantSupport(['users', 'posts']);
     */
    public function removeTenantSupport(?array $tableNames = null): array
    {
        $this->loadFromConfig();
        $configuredTables = $this->tables;

        if ($tableNames !== null) {
            $configuredTables = array_intersect_key(
                $configuredTables,
                array_flip($tableNames)
            );
        }

        $results = [];

        foreach ($configuredTables as $tableName => $config) {
            $results[$tableName] = $this->removeTenantFromTable($tableName, $config);
        }

        return $results;
    }

    /**
     * Process a single table to add tenant functionality.
     *
     * @param string $tableName The name of the table to process
     * @param TenantTableConfig $config The configuration for this table
     *
     * @return string Status of the operation
     */
    private function processTable(string $tableName, TenantTableConfig $config): string
    {
        if (!Schema::hasTable($tableName)) {
            return 'skipped_missing';
        }

        if (Schema::hasColumn($tableName, 'tenant_id')) {
            return 'skipped_exists';
        }

        try {
            $this->addTenantSupport($tableName, $config);

            return 'processed';
        } catch (Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Remove tenant support from a single table.
     *
     * @param string $tableName The name of the table to process
     * @param TenantTableConfig $config The configuration for this table
     *
     * @return string Status of the operation
     */
    private function removeTenantFromTable(string $tableName, TenantTableConfig $config): string
    {
        if (!Schema::hasTable($tableName)) {
            return 'skipped_missing';
        }

        if (!Schema::hasColumn($tableName, 'tenant_id')) {
            return 'skipped_no_tenant';
        }

        try {
            $this->removeTenantFromTableSchema($tableName, $config);

            return 'processed';
        } catch (Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Add tenant support to a table by adding a tenant_id column and constraints.
     *
     * @param string $tableName The table to modify
     * @param TenantTableConfig $config The tenant configuration for this table
     *
     * @throws Exception If the table modification fails
     */
    private function addTenantSupport(string $tableName, TenantTableConfig $config): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($config, $tableName) {
            $tenantIdColumn = $table->foreignId('tenant_id')
                ->after($config->after);

            if ($config->nullable) {
                $tenantIdColumn->nullable();
            }

            $tenantIdColumn->constrained('tenants');

            if ($config->cascadeDelete) {
                $tenantIdColumn->cascadeOnDelete();
            }

            $table->index('tenant_id');

            foreach ($config->dropForeignKeys as $fkName) {
                try {
                    $table->dropForeign($fkName);
                } catch (Exception) {
                    // Constraint might not exist, continue processing
                }
            }

            foreach ($config->dropUniques as $columns) {
                try {
                    $table->dropUnique($columns);
                } catch (Exception) {
                    // Constraint might not exist, continue processing
                }
            }

            foreach ($config->dropIndexes as $columns) {
                try {
                    $table->dropIndex($columns);
                } catch (Exception) {
                    // Index might not exist, continue processing
                }
            }

            foreach ($config->tenantUniqueConstraints as $columns) {
                $uniqueColumns = array_merge(['tenant_id'], $columns);
                $constraintName = $this->generateConstraintName($tableName, $columns, 'unique');

                $table->unique($uniqueColumns, $constraintName);
            }

            foreach ($config->tenantIndexes as $columns) {
                $indexColumns = array_merge(['tenant_id'], $columns);
                $indexName = $this->generateConstraintName($tableName, $columns, 'index');

                $table->index($indexColumns, $indexName);
            }

            foreach ($config->recreateForeignKeys as $fk) {
                $foreign = $table->foreign($fk['column'], $fk['name'] ?? null);

                if (isset($fk['references'])) {
                    $foreign->references($fk['references']);
                }

                if (isset($fk['on'])) {
                    $foreign->on($fk['on']);
                }

                if (isset($fk['onDelete'])) {
                    $foreign->onDelete($fk['onDelete']);
                }

                if (isset($fk['onUpdate'])) {
                    $foreign->onUpdate($fk['onUpdate']);
                }
            }
        });
    }

    /**
     * Remove tenant support from a table by removing the tenant_id column and constraints.
     *
     * @param string $tableName The table to modify
     * @param TenantTableConfig $config The tenant configuration for this table
     *
     * @throws Exception If the table modification fails
     */
    private function removeTenantFromTableSchema(string $tableName, TenantTableConfig $config): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($config, $tableName) {
            foreach ($config->tenantUniqueConstraints as $columns) {
                $constraintName = $this->generateConstraintName($tableName, $columns, 'unique');

                try {
                    $table->dropUnique($constraintName);
                } catch (Exception) {
                    // Constraint might not exist, continue processing
                }
            }

            foreach ($config->tenantIndexes as $columns) {
                $indexName = $this->generateConstraintName($tableName, $columns, 'index');

                try {
                    $table->dropIndex($indexName);
                } catch (Exception) {
                    // Index might not exist, continue processing
                }
            }

            foreach ($config->dropUniques as $columns) {
                try {
                    $table->unique($columns);
                } catch (Exception) {
                    // Constraint might already exist, continue processing
                }
            }

            foreach ($config->dropIndexes as $columns) {
                try {
                    $table->index($columns);
                } catch (Exception) {
                    // Index might already exist, continue processing
                }
            }

            $table->dropConstrainedForeignId('tenant_id');
        });
    }

    /**
     * Generate a consistent constraint name for tenant-scoped constraints and indexes.
     *
     * @param string $tableName The table name
     * @param array $columns The columns involved in the constraint
     * @param string $type The type of constraint ('unique' or 'index')
     *
     * @return string The generated constraint name
     */
    private function generateConstraintName(string $tableName, array $columns, string $type = 'unique'): string
    {
        $columnString = implode('_', $columns);

        return "{$tableName}_{$columnString}_tenant_{$type}";
    }

    /**
     * Register a table to be made tenant-aware.
     *
     * @param string $tableName The name of the table
     * @param bool|array<string, mixed>|TenantTableConfig|string $config Configuration for the table
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
     */
    public function registerMany(array $tables): void
    {
        foreach ($tables as $tableName => $config) {
            $this->register($tableName, $config);
        }
    }

    /**
     * Load tables from configuration.
     */
    public function loadFromConfig(): void
    {
        $tables = config('tenant.tables', []);

        if (config('tenant.queue.auto_tenant_id', false)) {
            $queueTables = [
                'jobs' => [
                    'after' => 'id',
                    'cascade_delete' => true,
                ],
                'failed_jobs' => [
                    'after' => 'id',
                    'cascade_delete' => true,
                ],
                'job_batches' => [
                    'after' => 'id',
                    'cascade_delete' => true,
                ],
            ];

            $tables = array_merge($queueTables, $tables);
        }

        if (config('tenant.sessions.auto_tenant_id', false)) {
            $sessionsTables = [
                'sessions' => [
                    'after' => 'id',
                    'cascade_delete' => true,
                ],
            ];

            $tables = array_merge($sessionsTables, $tables);
        }

        if (config('tenant.cache.auto_tenant_id', false)) {
            $cacheTables = [
                'cache' => [
                    'after' => 'key',
                    'cascade_delete' => true,
                ],
                'cache_locks' => [
                    'after' => 'key',
                    'cascade_delete' => true,
                ],
            ];

            $tables = array_merge($cacheTables, $tables);
        }

        if (config('tenant.password_reset_tokens.auto_tenant_id', false)) {
            $passwordResetTables = [
                'password_reset_tokens' => [
                    'after' => 'email',
                    'cascade_delete' => true,
                ],
            ];

            $tables = array_merge($passwordResetTables, $tables);
        }

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
     */
    public function getTable(string $tableName): ?TenantTableConfig
    {
        return $this->tables[$tableName] ?? null;
    }

    /**
     * Check if a table is registered.
     */
    public function hasTable(string $tableName): bool
    {
        return isset($this->tables[$tableName]);
    }

    /**
     * Get the count of registered tables.
     */
    public function count(): int
    {
        return count($this->tables);
    }

    /**
     * Add a tenant_id column to a Blueprint in a migration.
     *
     * This method is designed to be called from within a Schema::create or Schema::table
     * callback to add tenant functionality with fine-grained control.
     *
     * @param Blueprint $table The Blueprint instance
     * @param string $after Column to place tenant_id after (default: 'id')
     * @param bool $cascadeDelete Whether to cascade on tenant deletion (default: true)
     * @param array $dropUniques Unique constraints to drop before adding tenant-scoped ones
     * @param array $tenantUniqueConstraints Unique constraints that should include tenant_id
     *
     * @example
     * Schema::create('posts', function (Blueprint $table) {
     *     $table->id();
     *     TableRegistry::addTenantColumn($table);
     * });
     *
     * @example
     * Schema::create('posts', function (Blueprint $table) {
     *     $table->id();
     *     $table->string('slug');
     *     TableRegistry::addTenantColumn(
     *         $table,
     *         after: 'id',
     *         cascadeDelete: true,
     *         dropUniques: [['slug']],
     *         tenantUniqueConstraints: [['slug']]
     *     );
     * });
     */
    public static function addTenantColumn(
        Blueprint $table,
        string $after = 'id',
        bool $cascadeDelete = true,
        array $dropUniques = [],
        array $tenantUniqueConstraints = []
    ): void {
        $tenantIdColumn = $table->foreignId('tenant_id')
            ->after($after)
            ->constrained('tenants');

        if ($cascadeDelete) {
            $tenantIdColumn->cascadeOnDelete();
        }

        $table->index('tenant_id');

        foreach ($dropUniques as $columns) {
            try {
                $table->dropUnique($columns);
            } catch (Exception) {
                // Constraint might not exist in this context
            }
        }

        foreach ($tenantUniqueConstraints as $columns) {
            $uniqueColumns = array_merge(['tenant_id'], $columns);
            $table->unique($uniqueColumns);
        }
    }
}
