<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-level approval (user decision 2026-11-21): every approval stage now needs Level 1 (QC
 * Verifikator, the existing `approver` role) AND Level 2 (new `approver_l2` role: QC Team Leader /
 * QC Part Leader / Supervisor).
 *
 * Level 1 keeps living in the existing ipc_approvals columns (decision, approver_user_id, remarks,
 * approved_at), so everything that already reads them keeps working. Level 2 is stored in four new
 * nullable `l2_*` columns on the SAME row (one row per batch+stage, so the unique key is untouched).
 * A stage counts as approved only when both levels are Approved.
 *
 * ipc_approval_revisions gains `level` (1 = Level 1, 2 = Level 2) so the immutable history can tell
 * the two decisions apart.
 *
 * Data: every approval that is already Approved is copied to Level 2 (same approver and time), so no
 * batch that is already approved/completed becomes pending again. Additive only; nothing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_approvals', function (Blueprint $table) {
            $table->string('l2_decision')->nullable()->after('approved_at');
            $table->foreignId('l2_approver_user_id')->nullable()->after('l2_decision')->constrained('users');
            $table->text('l2_remarks')->nullable()->after('l2_approver_user_id');
            $table->timestamp('l2_decided_at')->nullable()->after('l2_remarks');
        });

        Schema::table('ipc_approval_revisions', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->after('revision_no');
        });

        if (! DB::table('roles')->where('code', 'approver_l2')->exists()) {
            DB::table('roles')->insert([
                'code' => 'approver_l2',
                'label' => 'Approver L2 (Team Leader / Supervisor)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('roles')->where('code', 'approver')->update(['label' => 'Approver L1 (QC Verifikator)']);

        // Backfill: already-approved stays approved at both levels. Query builder, so updated_at is untouched.
        DB::table('ipc_approvals')
            ->where('decision', 'Approved')
            ->whereNull('l2_decision')
            ->update([
                'l2_decision' => 'Approved',
                'l2_approver_user_id' => DB::raw('approver_user_id'),
                'l2_decided_at' => DB::raw('approved_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('ipc_approval_revisions', function (Blueprint $table) {
            $table->dropColumn('level');
        });

        Schema::table('ipc_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('l2_approver_user_id');
            $table->dropColumn(['l2_decision', 'l2_remarks', 'l2_decided_at']);
        });

        DB::table('users')->whereIn('role_id', DB::table('roles')->where('code', 'approver_l2')->select('id'))
            ->update(['role_id' => DB::table('roles')->where('code', 'approver')->value('id')]);
        DB::table('roles')->where('code', 'approver_l2')->delete();
        DB::table('roles')->where('code', 'approver')->update(['label' => 'Approver']);
    }
};
