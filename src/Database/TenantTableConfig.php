<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database;

/**
 * Configuration for making a database table tenant-aware.
 */
class TenantTableConfig
{
    public function __construct(
        /**
         * Column after which the tenant_id should be added.
         */
        public string $after = 'id',
        /**
         * Whether tenant deletion should cascade to this table.
         */
        public bool $cascadeDelete = true,
        /**
         * Whether the tenant_id column should be nullable.
         * Set to true for tables that may have records without tenant context
         * (e.g., Telescope entries from tinker, global logs, etc.).
         */
        public bool $nullable = false,
        /**
         * List of unique constraints to drop before adding tenant-specific ones.
         * Each entry is an array of columns that form a unique constraint.
         *
         * @var array<int, array<int, string>>
         */
        public array $dropUniques = [],
        /**
         * Unique constraints that should include tenant_id.
         * Each entry is an array of columns that should be unique together within a tenant.
         *
         * @var array<int, array<int, string>>
         */
        public array $tenantUniqueConstraints = [],
        /**
         * List of regular indexes to drop before adding tenant-specific ones.
         * Each entry is an array of columns that form an index.
         *
         * @var array<int, array<int, string>>
         */
        public array $dropIndexes = [],
        /**
         * Regular indexes that should include tenant_id.
         * Each entry is an array of columns that should be indexed together within a tenant.
         *
         * @var array<int, array<int, string>>
         */
        public array $tenantIndexes = [],
        /**
         * List of foreign key constraint names to drop before adding tenant_id.
         *
         * @var array<int, string>
         */
        public array $dropForeignKeys = [],
        /**
         * Foreign keys to recreate (optionally with tenant_id considerations).
         * Each entry contains a foreign key configuration.
         *
         * @var array<int, array<string, mixed>>
         */
        public array $recreateForeignKeys = [],
        /**
         * Whether to automatically detect and configure schema elements.
         * When true, indexes, uniques, and foreign keys are auto-detected from the table.
         */
        public bool $autoDetectSchema = false,
        /**
         * Whether tenant_id should be automatically added to all detected indexes.
         * Only applies when autoDetectSchema is true.
         */
        public bool $addTenantToAllIndexes = true,
        /**
         * Whether tenant_id should be automatically added to all detected unique constraints.
         * Only applies when autoDetectSchema is true.
         */
        public bool $addTenantToAllUniques = true,
        /**
         * Custom constraint name generator class or null for default naming.
         */
        public ?string $constraintNameGenerator = null,
    ) {
    }

    /**
     * Convert to array format for migrations.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'after' => $this->after,
            'cascade_delete' => $this->cascadeDelete,
            'nullable' => $this->nullable,
            'drop_uniques' => $this->dropUniques,
            'tenant_unique_constraints' => $this->tenantUniqueConstraints,
            'drop_indexes' => $this->dropIndexes,
            'tenant_indexes' => $this->tenantIndexes,
            'drop_foreign_keys' => $this->dropForeignKeys,
            'recreate_foreign_keys' => $this->recreateForeignKeys,
            'auto_detect_schema' => $this->autoDetectSchema,
            'add_tenant_to_all_indexes' => $this->addTenantToAllIndexes,
            'add_tenant_to_all_uniques' => $this->addTenantToAllUniques,
            'constraint_name_generator' => $this->constraintNameGenerator,
        ];
    }

    /**
     * Create from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): static
    {
        return new static(
            after: $config['after'] ?? 'id',
            cascadeDelete: $config['cascade_delete'] ?? true,
            nullable: $config['nullable'] ?? false,
            dropUniques: $config['drop_uniques'] ?? [],
            tenantUniqueConstraints: $config['tenant_unique_constraints'] ?? [],
            dropIndexes: $config['drop_indexes'] ?? [],
            tenantIndexes: $config['tenant_indexes'] ?? [],
            dropForeignKeys: $config['drop_foreign_keys'] ?? [],
            recreateForeignKeys: $config['recreate_foreign_keys'] ?? [],
            autoDetectSchema: $config['auto_detect_schema'] ?? false,
            addTenantToAllIndexes: $config['add_tenant_to_all_indexes'] ?? true,
            addTenantToAllUniques: $config['add_tenant_to_all_uniques'] ?? true,
            constraintNameGenerator: $config['constraint_name_generator'] ?? null,
        );
    }

    /**
     * Create with the default configuration.
     */
    public static function default(): static
    {
        return new static();
    }

    /**
     * Create configuration by introspecting an existing table.
     *
     * @param string $tableName The table to introspect
     * @param array<string, mixed> $overrides Manual overrides for auto-detected values
     * @param string|null $connection Database connection name
     */
    public static function fromTable(string $tableName, array $overrides = [], ?string $connection = null): static
    {
        $introspector = new SchemaIntrospector();
        $schema = $introspector->introspect($tableName, $connection);

        $regularIndexes = [];
        foreach ($schema['indexes'] as $index) {
            if (!$index['unique']) {
                $regularIndexes[] = $index['columns'];
            }
        }

        $config = [
            'after' => $introspector->suggestAfterColumn($tableName, $connection ?? config('database.default')),
            'cascade_delete' => true,
            'drop_uniques' => $schema['uniques'],
            'tenant_unique_constraints' => $schema['uniques'],
            'drop_indexes' => $regularIndexes,
            'tenant_indexes' => $regularIndexes,
            'drop_foreign_keys' => array_column($schema['foreign_keys'], 'name'),
            'recreate_foreign_keys' => $schema['foreign_keys'],
            'auto_detect_schema' => true,
        ];

        $config = array_merge($config, $overrides);

        return static::fromArray($config);
    }

    /**
     * Create a new instance for a table.
     *
     * @param string $tableName The table name
     */
    public static function for(string $tableName): static
    {
        return new static();
    }

    /**
     * Set the column after which tenant_id should be added.
     */
    public function after(string $column): static
    {
        $this->after = $column;

        return $this;
    }

    /**
     * Enable or disable cascade delete.
     */
    public function cascadeDelete(bool $enabled = true): static
    {
        $this->cascadeDelete = $enabled;

        return $this;
    }

    /**
     * Make tenant_id column nullable or not.
     */
    public function nullable(bool $enabled = true): static
    {
        $this->nullable = $enabled;

        return $this;
    }

    /**
     * Add a unique constraint to drop.
     */
    public function dropUnique(array $columns): static
    {
        $this->dropUniques[] = $columns;

        return $this;
    }

    /**
     * Add a tenant-scoped unique constraint.
     */
    public function tenantUnique(array $columns): static
    {
        $this->tenantUniqueConstraints[] = $columns;

        return $this;
    }

    /**
     * Add an index to drop.
     */
    public function dropIndex(array $columns): static
    {
        $this->dropIndexes[] = $columns;

        return $this;
    }

    /**
     * Add a tenant-scoped index.
     */
    public function tenantIndex(array $columns): static
    {
        $this->tenantIndexes[] = $columns;

        return $this;
    }

    /**
     * Add a foreign key to drop.
     */
    public function dropForeignKey(string $name): static
    {
        $this->dropForeignKeys[] = $name;

        return $this;
    }

    /**
     * Add a foreign key to recreate.
     */
    public function recreateForeignKey(
        string $column,
        string $references,
        string $on,
        string $onDelete = 'restrict',
        string $onUpdate = 'restrict',
        ?string $name = null
    ): static {
        $this->recreateForeignKeys[] = [
            'name' => $name,
            'column' => $column,
            'references' => $references,
            'on' => $on,
            'onDelete' => $onDelete,
            'onUpdate' => $onUpdate,
        ];

        return $this;
    }

    /**
     * Enable auto schema detection.
     */
    public function autoDetect(bool $enabled = true): static
    {
        $this->autoDetectSchema = $enabled;

        return $this;
    }
}
