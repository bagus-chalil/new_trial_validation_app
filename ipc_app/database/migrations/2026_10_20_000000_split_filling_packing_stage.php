<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the combined `filling_packing` approval/print stage into separate `filling` and
 * `packing` stages (user decision 2026-10-20: Filling's output is WIP, Packing's is FG — each gets
 * its own report and approval). Data-only; no schema change.
 *
 * Each existing `filling_packing` row is carried over to the sides its batch's flow actually has:
 * the Filling flow keeps it as `filling`, Packing + FG as `packing`, Full flow gets both (the
 * original row becomes `filling`, a copy — revisions included — becomes `packing`), so no batch
 * loses an approval or print and nothing already Approved falls back to pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $flowBatchIds = fn (string $flow) => fn ($query) => $query->select('id')->from('ipc_batches')->where('flow_type', $flow);

            foreach (['ipc_approvals', 'ipc_print_logs'] as $table) {
                DB::table($table)->where('stage', 'filling_packing')->whereIn('ipc_batch_id', $flowBatchIds('packing_fg'))->update(['stage' => 'packing']);
                DB::table($table)->where('stage', 'filling_packing')->whereIn('ipc_batch_id', $flowBatchIds('filling'))->update(['stage' => 'filling']);
            }

            // Whatever is left belongs to Full-flow batches: the row stays as `filling`, a copy is `packing`.
            // chunkById (not each/chunk): the loop changes the very rows its WHERE matches.
            DB::table('ipc_approvals')->where('stage', 'filling_packing')->chunkById(500, fn ($approvals) => $approvals->each(function ($approval) {
                $row = (array) $approval;
                unset($row['id']);
                $copyId = DB::table('ipc_approvals')->insertGetId([...$row, 'stage' => 'packing']);

                DB::table('ipc_approval_revisions')->where('ipc_approval_id', $approval->id)->orderBy('id')->each(function ($revision) use ($copyId) {
                    $revisionRow = (array) $revision;
                    unset($revisionRow['id']);
                    DB::table('ipc_approval_revisions')->insert([...$revisionRow, 'ipc_approval_id' => $copyId]);
                });

                DB::table('ipc_approvals')->where('id', $approval->id)->update(['stage' => 'filling']);
            }));

            DB::table('ipc_print_logs')->where('stage', 'filling_packing')->chunkById(500, fn ($logs) => $logs->each(function ($log) {
                $row = (array) $log;
                unset($row['id']);
                DB::table('ipc_print_logs')->insert([...$row, 'stage' => 'packing']);
                DB::table('ipc_print_logs')->where('id', $log->id)->update(['stage' => 'filling']);
            }));
        });
    }

    /**
     * Best-effort reverse: a batch's `packing` row is dropped when it also has a `filling` row
     * (Full flow collapses back to one), otherwise it becomes `filling_packing` on its own.
     */
    public function down(): void
    {
        DB::transaction(function () {
            foreach (['ipc_approvals', 'ipc_print_logs'] as $table) {
                DB::table($table)->where('stage', 'packing')->chunkById(500, fn ($rows) => $rows->each(function ($row) use ($table) {
                    $hasFilling = DB::table($table)->where('ipc_batch_id', $row->ipc_batch_id)->where('stage', 'filling')->exists();

                    $hasFilling
                        ? DB::table($table)->where('id', $row->id)->delete()
                        : DB::table($table)->where('id', $row->id)->update(['stage' => 'filling_packing']);
                }));

                DB::table($table)->where('stage', 'filling')->update(['stage' => 'filling_packing']);
            }
        });
    }
};
