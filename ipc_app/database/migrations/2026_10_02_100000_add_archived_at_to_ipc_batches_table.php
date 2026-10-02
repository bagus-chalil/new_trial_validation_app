<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            // Finished batches can be moved out of the active Batch list into the Archive
            // (still viewable/printable — unlike deleted_at, archiving hides nothing else).
            $table->timestamp('archived_at')->nullable()->after('current_stage');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();

            // Batch list: stage filter + soft-delete scope + archived filter + latest('id'),
            // same reasoning as ipc_batches_stage_deleted_id_index (no standalone index on a
            // mostly-NULL column).
            $table->index(['current_stage', 'deleted_at', 'archived_at', 'id'], 'ipc_batches_stage_deleted_archived_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->dropIndex('ipc_batches_stage_deleted_archived_id_index');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archived_at');
        });
    }
};
