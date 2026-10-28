<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The previous migration marked already-complete Micro Checks (on batches whose Finished
        // Good is finalized) as finalized, but left those batches at the Finished stage — now that
        // Micro Check's Selesaikan is what hands a batch to Approval, they would be stuck: locked
        // Micro Check, nothing left to press. Move them on.
        DB::table('ipc_batches')
            ->where('current_stage', 'finished')
            ->whereIn('id', DB::table('finished_checks')->whereNotNull('completed_at')->select('ipc_batch_id'))
            ->whereIn('id', DB::table('micro_checks')->whereNotNull('completed_at')->select('ipc_batch_id'))
            ->update(['current_stage' => 'approval']);
    }

    public function down(): void
    {
        // Data fix only; nothing to undo.
    }
};
