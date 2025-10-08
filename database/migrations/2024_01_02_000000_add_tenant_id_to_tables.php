<?php

use Illuminate\Database\Migrations\Migration;
use Quvel\Tenant\Contracts\TableRegistry;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Processes all configured tables to add tenant functionality.
     * Automatically skips tables that already have tenant_id columns.
     */
    public function up(): void
    {
        $registry = app(TableRegistry::class);

        $registry->processTables();
    }

    /**
     * Reverse the migrations.
     *
     * Removes tenant functionality from all configured tables.
     * Automatically skips tables that don't have tenant_id columns.
     */
    public function down(): void
    {
        $registry = app(TableRegistry::class);

        $registry->removeTenantSupport();
    }
};