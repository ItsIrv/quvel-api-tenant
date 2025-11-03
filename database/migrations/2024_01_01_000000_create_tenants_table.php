<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Quvel\Tenant\Models\Tenant;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('tenant.table_name', 'tenants');

        Schema::create($tableName, static function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->foreignIdFor(tenant_class(), 'parent_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_internal')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
            $table->index('identifier');
        });
    }

    public function down(): void
    {
        $tableName = config('tenant.table_name', 'tenants');

        Schema::dropIfExists($tableName);
    }
};