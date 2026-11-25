<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check Tooling: per-request signer assignment. `assignees` holds
 * {prni|rni|pi|production: user id}; a missing/empty key means "anyone in that
 * department group may sign". Additive and guarded like every pm_* migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_toolings') && ! Schema::hasColumn('pm_toolings', 'assignees')) {
            Schema::table('pm_toolings', function (Blueprint $table) {
                $table->json('assignees')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Never drop pm_* columns from a migration: they may hold migrated data.
    }
};
