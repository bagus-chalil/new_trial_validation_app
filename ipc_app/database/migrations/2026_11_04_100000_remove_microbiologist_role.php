<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Micro Check no longer gates Finished Good, so batches that finished Finished Good but
        // were still waiting on it would be stuck at the Finished stage — hand them to Approval.
        DB::table('ipc_batches')
            ->where('current_stage', 'finished')
            ->whereIn('id', DB::table('finished_checks')->whereNotNull('completed_at')->select('ipc_batch_id'))
            ->update(['current_stage' => 'approval']);

        $microId = DB::table('roles')->where('code', 'microbiologist')->value('id');
        if (! $microId) {
            return;
        }

        // users.role_id is NOT NULL + restrictOnDelete, so existing Microbiologist accounts must
        // move to another role before the row can go. They land on the least-privileged workflow
        // role (Staff) *and are deactivated*, so nobody gains access silently — an Admin decides
        // per account whether to re-enable it.
        $staffId = DB::table('roles')->where('code', 'staff')->value('id');
        if ($staffId) {
            DB::table('users')->where('role_id', $microId)->update(['role_id' => $staffId, 'is_active' => false]);
        }

        DB::table('roles')->where('id', $microId)->delete();
    }

    public function down(): void
    {
        // micro_checks / micro_check_revisions are intentionally kept (historical data), so only
        // the role row comes back; reassigning users and re-advancing batches is not reversible.
        DB::table('roles')->insertOrIgnore([
            ['code' => 'microbiologist', 'label' => 'Microbiologist', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
