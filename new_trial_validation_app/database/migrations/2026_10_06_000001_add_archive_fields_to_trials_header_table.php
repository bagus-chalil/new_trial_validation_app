<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archive — a separate, non-deletion concept from the existing
     * deleted_at/deleted_by soft-delete pair (see TrashController's doc
     * comment): hides a finished (Approved/Rejected) trial from the normal
     * status lists/dashboard without removing it or its history, mirroring
     * ipc_app's `archived_at`/`archived_by` columns on `ipc_batches`
     * (2026_10_02_100000_add_archived_at_to_ipc_batches_table.php) — same
     * shape, same no-FK-on-shared-tables convention as every other ALTER
     * migration on this shared `trials_header` table.
     */
    public function up(): void
    {
        Schema::table('trials_header', function (Blueprint $table) {
            if (! Schema::hasColumn('trials_header', 'archived_at')) {
                $table->dateTime('archived_at')->nullable();
            }
            if (! Schema::hasColumn('trials_header', 'archived_by')) {
                $table->unsignedInteger('archived_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trials_header', function (Blueprint $table) {
            if (Schema::hasColumn('trials_header', 'archived_by')) {
                $table->dropColumn('archived_by');
            }
            if (Schema::hasColumn('trials_header', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
