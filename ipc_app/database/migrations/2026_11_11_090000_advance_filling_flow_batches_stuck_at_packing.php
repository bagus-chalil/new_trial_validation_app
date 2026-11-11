<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // "Selesaikan pakai riwayat terakhir" on Filling Check used to hardcode the next stage as
        // Packing, so Filling-only batches (no Packing / Finished Good stage) ended up parked at
        // `packing` with nowhere to go. Their Filling Check is finalized, so the real next stage
        // is Approval. Only touches Filling-flow batches that have no Packing/Finished rows.
        DB::table('ipc_batches')
            ->where('flow_type', 'filling')
            ->where('current_stage', 'packing')
            ->whereIn('id', DB::table('filling_checks')->whereNotNull('completed_at')->select('ipc_batch_id'))
            ->whereNotIn('id', DB::table('packing_checks')->select('ipc_batch_id'))
            ->whereNotIn('id', DB::table('finished_checks')->select('ipc_batch_id'))
            ->update(['current_stage' => 'approval']);
    }

    public function down(): void
    {
        // Data fix only; nothing to undo.
    }
};
