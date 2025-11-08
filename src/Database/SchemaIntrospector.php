<?php

declare(strict_types=1);

namespace Quvel\Tenant\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introspects database tables to detect schema elements for tenant configuration.
 */
class SchemaIntrospector
{
    /**
     * Introspect a table and return its schema elements.
     *
     * @param string $tableName
     * @param string|null $connection
     * @return array{
     *     columns: array<string>,
     *     primary_key: string|array<string>|null,
     *     indexes: array<int, array{name: string, columns: array<string>, unique: bool}>,
     *     uniques: array<int, array<string>>,
     *     foreign_keys: array<int, array{name: string, column: string, references: string, on: string, onUpdate: string, onDelete: string}>
     * }
     */
    public function introspect(string $tableName, ?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        return [
            'columns' => $this->getColumns($tableName, $connection),
            'primary_key' => $this->getPrimaryKey($tableName, $connection),
            'indexes' => $this->getIndexes($tableName, $connection),
            'uniques' => $this->getUniqueConstraints($tableName, $connection),
            'foreign_keys' => $this->getForeignKeys($tableName, $connection),
        ];
    }

    /**
     * Get all column names from a table.
     *
     * @param string $tableName
     * @param string $connection
     * @return array<string>
     */
    protected function getColumns(string $tableName, string $connection): array
    {
        return Schema::connection($connection)->getColumnListing($tableName);
    }

    /**
     * Detect the primary key column(s).
     *
     * @param string $tableName
     * @param string $connection
     * @return string|array<string>|null
     */
    protected function getPrimaryKey(string $tableName, string $connection): string|array|null
    {
        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'mysql') {
            $result = DB::connection($connection)->select(
                "
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND CONSTRAINT_NAME = 'PRIMARY'
                ORDER BY ORDINAL_POSITION
            ",
                [$tableName]
            );

            $columns = array_map(fn ($row) => $row->COLUMN_NAME, $result);

            return match (count($columns)) {
                0 => null,
                1 => $columns[0],
                default => $columns,
            };
        }

        if ($driver === 'pgsql') {
            $result = DB::connection($connection)->select(
                '
                SELECT a.attname
                FROM pg_index i
                JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = ?::regclass AND i.indisprimary
                ORDER BY a.attnum
            ',
                [$tableName]
            );

            $columns = array_map(fn ($row) => $row->attname, $result);

            return match (count($columns)) {
                0 => null,
                1 => $columns[0],
                default => $columns,
            };
        }

        if ($driver === 'sqlite') {
            $result = DB::connection($connection)->select("PRAGMA table_info({$tableName})");

            $columns = [];
            foreach ($result as $row) {
                if ($row->pk) {
                    $columns[] = $row->name;
                }
            }

            return match (count($columns)) {
                0 => null,
                1 => $columns[0],
                default => $columns,
            };
        }

        if ($driver === 'sqlsrv') {
            $result = DB::connection($connection)->select(
                '
                SELECT c.name
                FROM sys.indexes i
                JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
                WHERE i.object_id = OBJECT_ID(?) AND i.is_primary_key = 1
                ORDER BY ic.key_ordinal
            ',
                [$tableName]
            );

            $columns = array_map(fn ($row) => $row->name, $result);

            return match (count($columns)) {
                0 => null,
                1 => $columns[0],
                default => $columns,
            };
        }

        // Fallback: try to detect 'id' column
        $columns = $this->getColumns($tableName, $connection);

        return in_array('id', $columns, true) ? 'id' : ($columns[0] ?? null);
    }

    /**
     * Get all indexes (including unique).
     *
     * @param string $tableName
     * @param string $connection
     * @return array<int, array{name: string, columns: array<string>, unique: bool}>
     */
    protected function getIndexes(string $tableName, string $connection): array
    {
        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'mysql') {
            $results = DB::connection($connection)->select(
                "
                SELECT
                    INDEX_NAME as name,
                    COLUMN_NAME as column_name,
                    NON_UNIQUE as non_unique,
                    SEQ_IN_INDEX as seq
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND INDEX_NAME != 'PRIMARY'
                ORDER BY INDEX_NAME, SEQ_IN_INDEX
            ",
                [$tableName]
            );

            $indexes = [];
            foreach ($results as $row) {
                $indexes[$row->name]['name'] = $row->name;
                $indexes[$row->name]['columns'][] = $row->column_name;
                $indexes[$row->name]['unique'] = $row->non_unique == 0;
            }

            return array_values($indexes);
        }

        if ($driver === 'pgsql') {
            $results = DB::connection($connection)->select(
                '
                SELECT
                    i.relname as name,
                    a.attname as column_name,
                    ix.indisunique as is_unique,
                    a.attnum as seq
                FROM pg_class t
                JOIN pg_index ix ON t.oid = ix.indrelid
                JOIN pg_class i ON i.oid = ix.indexrelid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
                WHERE t.relname = ?
                    AND NOT ix.indisprimary
                ORDER BY i.relname, a.attnum
            ',
                [$tableName]
            );

            $indexes = [];
            foreach ($results as $row) {
                $indexes[$row->name]['name'] = $row->name;
                $indexes[$row->name]['columns'][] = $row->column_name;
                $indexes[$row->name]['unique'] = $row->is_unique;
            }

            return array_values($indexes);
        }

        if ($driver === 'sqlite') {
            $indexList = DB::connection($connection)->select("PRAGMA index_list({$tableName})");

            $indexes = [];
            foreach ($indexList as $idx) {
                // Skip auto-created indexes for primary keys
                if (str_starts_with($idx->name, 'sqlite_autoindex_')) {
                    continue;
                }

                $indexInfo = DB::connection($connection)->select("PRAGMA index_info({$idx->name})");
                $indexes[$idx->name] = [
                    'name' => $idx->name,
                    'columns' => array_map(fn ($col) => $col->name, $indexInfo),
                    'unique' => (bool) $idx->unique,
                ];
            }

            return array_values($indexes);
        }

        if ($driver === 'sqlsrv') {
            $results = DB::connection($connection)->select(
                '
                SELECT
                    i.name,
                    c.name as column_name,
                    i.is_unique,
                    ic.key_ordinal
                FROM sys.indexes i
                JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
                WHERE i.object_id = OBJECT_ID(?) AND i.is_primary_key = 0
                ORDER BY i.name, ic.key_ordinal
            ',
                [$tableName]
            );

            $indexes = [];
            foreach ($results as $row) {
                $indexes[$row->name]['name'] = $row->name;
                $indexes[$row->name]['columns'][] = $row->column_name;
                $indexes[$row->name]['unique'] = (bool) $row->is_unique;
            }

            return array_values($indexes);
        }

        return [];
    }

    /**
     * Get unique constraints (excluding primary key).
     *
     * @param string $tableName
     * @param string $connection
     * @return array<int, array<string>>
     */
    protected function getUniqueConstraints(string $tableName, string $connection): array
    {
        $indexes = $this->getIndexes($tableName, $connection);

        $uniques = [];
        foreach ($indexes as $index) {
            if ($index['unique']) {
                $uniques[] = $index['columns'];
            }
        }

        return $uniques;
    }

    /**
     * Get foreign key constraints.
     *
     * @param string $tableName
     * @param string $connection
     * @return array<int, array{name: string, column: string, references: string, on: string, onUpdate: string, onDelete: string}>
     */
    protected function getForeignKeys(string $tableName, string $connection): array
    {
        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'mysql') {
            $results = DB::connection($connection)->select(
                '
                SELECT
                    kcu.CONSTRAINT_NAME as name,
                    kcu.COLUMN_NAME as `column`,
                    kcu.REFERENCED_COLUMN_NAME as `references`,
                    kcu.REFERENCED_TABLE_NAME as `on`,
                    rc.UPDATE_RULE as onUpdate,
                    rc.DELETE_RULE as onDelete
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
                WHERE kcu.TABLE_SCHEMA = DATABASE()
                    AND kcu.TABLE_NAME = ?
                    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ',
                [$tableName]
            );

            return array_map(static fn ($row) => [
                'name' => $row->name,
                'column' => $row->column,
                'references' => $row->references,
                'on' => $row->on,
                'onUpdate' => strtolower($row->onUpdate ?? 'restrict'),
                'onDelete' => strtolower($row->onDelete ?? 'restrict'),
            ], $results);
        }

        if ($driver === 'pgsql') {
            $results = DB::connection($connection)->select(
                "
                SELECT
                    con.conname as name,
                    att.attname as column,
                    ref_att.attname as references,
                    ref_class.relname as on,
                    CASE con.confupdtype
                        WHEN 'a' THEN 'no action'
                        WHEN 'r' THEN 'restrict'
                        WHEN 'c' THEN 'cascade'
                        WHEN 'n' THEN 'set null'
                        WHEN 'd' THEN 'set default'
                    END as onUpdate,
                    CASE con.confdeltype
                        WHEN 'a' THEN 'no action'
                        WHEN 'r' THEN 'restrict'
                        WHEN 'c' THEN 'cascade'
                        WHEN 'n' THEN 'set null'
                        WHEN 'd' THEN 'set default'
                    END as onDelete
                FROM pg_constraint con
                JOIN pg_class class ON con.conrelid = class.oid
                JOIN pg_attribute att ON att.attrelid = class.oid AND att.attnum = ANY(con.conkey)
                JOIN pg_class ref_class ON con.confrelid = ref_class.oid
                JOIN pg_attribute ref_att ON ref_att.attrelid = ref_class.oid AND ref_att.attnum = ANY(con.confkey)
                WHERE class.relname = ?
                    AND con.contype = 'f'
            ",
                [$tableName]
            );

            return array_map(fn ($row) => [
                'name' => $row->name,
                'column' => $row->column,
                'references' => $row->references,
                'on' => $row->on,
                'onUpdate' => $row->onupdate ?? 'restrict',
                'onDelete' => $row->ondelete ?? 'restrict',
            ], $results);
        }

        if ($driver === 'sqlite') {
            $results = DB::connection($connection)->select("PRAGMA foreign_key_list({$tableName})");

            return array_map(fn ($row) => [
                'name' => "fk_{$tableName}_{$row->from}_{$row->id}",
                'column' => $row->from,
                'references' => $row->to,
                'on' => $row->table,
                'onUpdate' => strtolower($row->on_update),
                'onDelete' => strtolower($row->on_delete),
            ], $results);
        }

        if ($driver === 'sqlsrv') {
            $results = DB::connection($connection)->select(
                '
                SELECT
                    fk.name,
                    COL_NAME(fkc.parent_object_id, fkc.parent_column_id) as column_name,
                    COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) as references,
                    OBJECT_NAME(fkc.referenced_object_id) as ref_table,
                    fk.update_referential_action_desc as on_update,
                    fk.delete_referential_action_desc as on_delete
                FROM sys.foreign_keys fk
                JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
                WHERE fk.parent_object_id = OBJECT_ID(?)
            ',
                [$tableName]
            );

            return array_map(fn ($row) => [
                'name' => $row->name,
                'column' => $row->column_name,
                'references' => $row->references,
                'on' => $row->ref_table,
                'onUpdate' => strtolower(str_replace('_', ' ', $row->on_update)),
                'onDelete' => strtolower(str_replace('_', ' ', $row->on_delete)),
            ], $results);
        }

        return [];
    }

    /**
     * Suggest the column after which tenant_id should be added.
     *
     * @param string $tableName
     * @param string $connection
     * @return string
     */
    public function suggestAfterColumn(string $tableName, string $connection): string
    {
        $primaryKey = $this->getPrimaryKey($tableName, $connection);

        if (is_string($primaryKey)) {
            return $primaryKey;
        }

        if (is_array($primaryKey) && count($primaryKey) > 0) {
            return $primaryKey[0];
        }

        return 'id';
    }
}
