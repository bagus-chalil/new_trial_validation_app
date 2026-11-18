<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves batches stuck at `approval` to `completed` when every approval their flow requires is
 * already Approved (data cleanup, same job as `php artisan ipc:advance-approved-batches`).
 *
 * Why they exist: only SaveApproval advances a batch, so batches approved before the
 * approval-rule changes (and the 2026-10-20 Filling/Packing split) never moved. They are in no
 * Approval Queue (nothing is pending) yet still counted as "at Approval" on the dashboard, which
 * made its numbers disagree with the queue (79 vs 54 on the production copy; 25 Filling-only batches).
 *
 * The required approval stages per flow are frozen here on purpose (rules as of 2026-11-18, same as
 * IpcApproval::requiredStagesFor()), so this migration keeps doing the same thing if the model changes.
 *
 * It changes `current_stage` ONLY: no approval/print-log row is created or removed, `updated_at` is
 * left alone (the dashboard's "Selesai hari ini" counts by updated_at and must not jump), soft-deleted
 * batches are skipped, and a batch with any non-Approved approval row is skipped as a guard.
 * Safe to re-run: it only touches batches still at `approval`.
 *
 * Rollback bookkeeping: moved ids are kept in `ipc_approved_batches_completed` so down() restores
 * exactly those batches. The table can be dropped once the result is verified.
 */
return new class extends Migration
{
    private const TRACKING_TABLE = 'ipc_approved_batches_completed';

    /** flow_type => approval stages that must all be Approved. */
    private const REQUIRED_BY_FLOW = [
        'filling' => ['filling'],
        'packing_fg' => ['startup', 'packing', 'finished'],
        'full' => ['startup', 'filling', 'packing', 'finished'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TRACKING_TABLE)) {
            Schema::create(self::TRACKING_TABLE, function (Blueprint $table) {
                $table->unsignedBigInteger('ipc_batch_id')->primary();
                $table->timestamp('migrated_at')->nullable();
            });
        }

        DB::transaction(function () {
            foreach (self::REQUIRED_BY_FLOW as $flow => $stages) {
                $query = DB::table('ipc_batches')
                    ->where('current_stage', 'approval')
                    ->where('flow_type', $flow)
                    ->whereNull('deleted_at')
                    // Guard: any approval row that is not Approved keeps the batch where it is.
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                        ->from('ipc_approvals')
                        ->whereColumn('ipc_approvals.ipc_batch_id', 'ipc_batches.id')
                        ->where(fn ($w) => $w->where('ipc_approvals.decision', '!=', 'Approved')->orWhereNull('ipc_approvals.decision')));

                foreach ($stages as $stage) {
                    $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                        ->from('ipc_approvals')
                        ->whereColumn('ipc_approvals.ipc_batch_id', 'ipc_batches.id')
                        ->where('ipc_approvals.stage', $stage)
                        ->where('ipc_approvals.decision', 'Approved'));
                }

                $query->orderBy('id')->select('id')->chunkById(500, function ($batches) {
                    $ids = $batches->pluck('id')->all();

                    DB::table(self::TRACKING_TABLE)->insertOrIgnore(
                        array_map(fn ($id) => ['ipc_batch_id' => $id, 'migrated_at' => now()], $ids),
                    );

                    // Query builder (not Eloquent) on purpose: updated_at stays untouched.
                    DB::table('ipc_batches')->whereIn('id', $ids)->where('current_stage', 'approval')->update(['current_stage' => 'completed']);
                });
            }
        });
    }

    public function down(): void
    {
        $this->restoreBatches();

        Schema::dropIfExists(self::TRACKING_TABLE);
    }

    /** Puts exactly the batches this migration moved back at `approval` (no DDL, so it is testable). */
    public function restoreBatches(): void
    {
        if (! Schema::hasTable(self::TRACKING_TABLE)) {
            return;
        }

        DB::table(self::TRACKING_TABLE)->orderBy('ipc_batch_id')->select('ipc_batch_id')->chunk(500, function ($rows) {
            DB::table('ipc_batches')
                ->whereIn('id', $rows->pluck('ipc_batch_id')->all())
                ->where('current_stage', 'completed')
                ->update(['current_stage' => 'approval']);
        });
    }
};
