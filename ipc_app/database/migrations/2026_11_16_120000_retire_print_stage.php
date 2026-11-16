<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires Print as a workflow stage (user decision 2026-11-16): a batch is Completed as soon as
 * every required approval is Approved, and printing is just a logged action (ipc_print_logs).
 * Every batch still sitting at `print` moves to `completed` — ~465 on production, most of them
 * stuck only because nobody had opened every report PDF.
 *
 * Rule-based (not an id list) so batches that reached `print` between the data copy and deploy
 * are handled too. It changes `current_stage` ONLY:
 *  - no approval / print-log row is created, changed or removed (batches approved before Startup
 *    approval became a required part have no Startup approval row, and none is invented here);
 *  - `updated_at` is left alone, so "Selesai hari ini" on the dashboard doesn't jump;
 *  - soft-deleted batches are skipped (Recycle Bin contents stay as they were);
 *  - a batch with any approval that is not Approved (Rejected / blank) is skipped as a guard.
 * Safe to re-run: it only touches batches still at `print`.
 *
 * Rollback bookkeeping: the moved ids are kept in `ipc_print_stage_migrated` so down() can put
 * exactly those batches back at `print`. The table can be dropped once the result is verified.
 */
return new class extends Migration
{
    private const TRACKING_TABLE = 'ipc_print_stage_migrated';

    public function up(): void
    {
        if (! Schema::hasTable(self::TRACKING_TABLE)) {
            Schema::create(self::TRACKING_TABLE, function (Blueprint $table) {
                $table->unsignedBigInteger('ipc_batch_id')->primary();
                $table->timestamp('migrated_at')->nullable();
            });
        }

        DB::transaction(function () {
            DB::table('ipc_batches')
                ->where('current_stage', 'print')
                ->whereNull('deleted_at')
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('ipc_approvals')
                    ->whereColumn('ipc_approvals.ipc_batch_id', 'ipc_batches.id')
                    ->where(fn ($q) => $q->where('ipc_approvals.decision', '!=', 'Approved')->orWhereNull('ipc_approvals.decision')))
                ->orderBy('id')
                ->select('id')
                ->chunkById(500, function ($batches) {
                    $ids = $batches->pluck('id')->all();

                    DB::table(self::TRACKING_TABLE)->insertOrIgnore(
                        array_map(fn ($id) => ['ipc_batch_id' => $id, 'migrated_at' => now()], $ids),
                    );

                    // Query builder (not Eloquent) on purpose: updated_at stays untouched.
                    DB::table('ipc_batches')->whereIn('id', $ids)->where('current_stage', 'print')->update(['current_stage' => 'completed']);
                });
        });
    }

    public function down(): void
    {
        $this->restoreBatches();

        Schema::dropIfExists(self::TRACKING_TABLE);
    }

    /** Puts exactly the batches this migration moved back at `print` (no DDL, so it is testable). */
    public function restoreBatches(): void
    {
        if (! Schema::hasTable(self::TRACKING_TABLE)) {
            return;
        }

        DB::table(self::TRACKING_TABLE)->orderBy('ipc_batch_id')->select('ipc_batch_id')->chunk(500, function ($rows) {
            DB::table('ipc_batches')
                ->whereIn('id', $rows->pluck('ipc_batch_id')->all())
                ->where('current_stage', 'completed')
                ->update(['current_stage' => 'print']);
        });
    }
};
