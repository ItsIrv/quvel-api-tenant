<?php

use Illuminate\Database\Migrations\Migration;
use Quvel\Tenant\Managers\TenantTableManager;

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
        $manager = app(TenantTableManager::class);

        $manager->processTables();
    }

    /**
     * Reverse the migrations.
     *
     * Removes tenant functionality from all configured tables.
     * Automatically skips tables that don't have tenant_id columns.
     */
    public function down(): void
    {
        $manager = app(TenantTableManager::class);

        $manager->removeTenantSupport();
    }
};