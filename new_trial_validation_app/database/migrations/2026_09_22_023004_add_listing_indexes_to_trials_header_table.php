<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `trials_header` (shared with the legacy app) has no index at all on
     * `deleted_at`/`progress_status`/`created_at`/`updated_at` — every list
     * page (TrialController::index, DashboardController::index,
     * ReportController's 4 list queries, ApprovalController::index, etc.)
     * filters `deleted_at IS NULL` (+ often `progress_status = ...`) and
     * orders by `created_at`/`updated_at` DESC, which today means a full
     * table scan + filesort on every request. Fine at the current ~100 rows,
     * not fine once this table reaches the 50,000+ scale it's designed to
     * paginate. Two composite indexes cover the two ordering columns actually
     * used across the app, both led by `deleted_at` since every list query
     * filters on it first (including Trash, which filters `deleted_at IS NOT
     * NULL` — MySQL indexes NULL as an ordinary value, so the same leading
     * column still narrows that scan too).
     *
     * Guarded with `hasIndex()`/`hasColumn()` (not the usual
     * `Schema::hasTable()`-skip-entirely pattern) since this table already
     * exists on the shared DB — safe to re-run after a partial failure, same
     * convention as every other ALTER migration against a shared table in
     * this app.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('trials_header', 'deleted_at')) {
            return;
        }

        if (! Schema::hasIndex('trials_header', ['deleted_at', 'progress_status', 'created_at'])) {
            Schema::table('trials_header', function (Blueprint $table) {
                $table->index(['deleted_at', 'progress_status', 'created_at'], 'idx_trials_status_created');
            });
        }

        if (! Schema::hasIndex('trials_header', ['deleted_at', 'progress_status', 'updated_at'])) {
            Schema::table('trials_header', function (Blueprint $table) {
                $table->index(['deleted_at', 'progress_status', 'updated_at'], 'idx_trials_status_updated');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('trials_header', ['deleted_at', 'progress_status', 'created_at'])) {
            Schema::table('trials_header', function (Blueprint $table) {
                $table->dropIndex('idx_trials_status_created');
            });
        }

        if (Schema::hasIndex('trials_header', ['deleted_at', 'progress_status', 'updated_at'])) {
            Schema::table('trials_header', function (Blueprint $table) {
                $table->dropIndex('idx_trials_status_updated');
            });
        }
    }
};
