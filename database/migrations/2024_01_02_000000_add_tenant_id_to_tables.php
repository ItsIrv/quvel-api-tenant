<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Quvel\Tenant\Database\TenantTableRegistry;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $registry = app(TenantTableRegistry::class);
        $registry->loadFromConfig();

        $tables = $registry->getTables();

        foreach ($tables as $tableName => $config) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($config, $tableName) {
                $tenantIdColumn = $table->foreignId('tenant_id')
                    ->after($config->after)
                    ->constrained('tenants');

                if ($config->cascadeDelete) {
                    $tenantIdColumn->cascadeOnDelete();
                }

                foreach ($config->dropUniques as $columns) {
                    $table->dropUnique($columns);
                }

                foreach ($config->tenantUniqueConstraints as $columns) {
                    $uniqueColumns = array_merge(['tenant_id'], $columns);
                    $constraintName = $tableName . '_' . implode('_', $columns) . '_tenant_unique';

                    $table->unique($uniqueColumns, $constraintName);
                }

                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $registry = app(TenantTableRegistry::class);
        $registry->loadFromConfig();

        $tables = $registry->getTables();

        foreach ($tables as $tableName => $config) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($tableName, $config) {
                foreach ($config->tenantUniqueConstraints as $columns) {
                    $constraintName = $tableName . '_' . implode('_', $columns) . '_tenant_unique';
                    $table->dropUnique($constraintName);
                }

                foreach ($config->dropUniques as $columns) {
                    $table->unique($columns);
                }

                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};