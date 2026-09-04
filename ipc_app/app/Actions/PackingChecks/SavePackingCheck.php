<?php

namespace App\Actions\PackingChecks;

use App\Models\IpcBatch;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SavePackingCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): PackingCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $finalize = (bool) ($data['finalize'] ?? false);
            $existing = $batch->packingCheck;
            $fields = collect($data)->except(['finalize', 'line_leader_name', 'coding_machine'])->all();

            // Asked once on the first round, then carried forward untouched — the coding machine
            // and line leader don't change between inspection rounds of the same batch, so the
            // form stops rendering them from TH_PROGRESS 2 on and submits nothing for them. A
            // first round that left one blank can still fill it in later.
            $lineLeaderName = $existing?->line_leader_name ?? ($data['line_leader_name'] ?? null);
            $codingMachine = $existing?->coding_machine ?? ($data['coding_machine'] ?? null);

            $saveCount = ($existing?->save_count ?? 0) + 1;

            $packingCheck = PackingCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$fields,
                    'standard_weight_mb' => self::standardWeightMbFor($batch),
                    'line_leader_name' => $lineLeaderName,
                    'coding_machine' => $codingMachine,
                    'user_id' => $user->id,
                    // TH_PROGRESS: counts every save, draft or final (real legacy column).
                    'save_count' => $saveCount,
                    'completed_at' => $finalize ? now() : null,
                ],
            );

            // Immutable snapshot of this exact round — packing_checks itself only ever holds the
            // current state, so without this an earlier round's checklist answers and remarks
            // would be lost to the next save. The two models share column names for everything
            // being snapshotted, so the copy is a straight fillable-keyed lift off the row we
            // just wrote — the four keys below are the only ones unique to a revision.
            PackingCheckRevision::create([
                ...collect($packingCheck->getAttributes())
                    ->only((new PackingCheckRevision)->getFillable())
                    ->all(),
                'packing_check_id' => $packingCheck->id,
                'revision_no' => $saveCount,
                'finalize' => $finalize,
                'user_id' => $user->id,
            ]);

            if ($finalize && $batch->current_stage === IpcBatch::STAGE_PACKING) {
                $batch->update(['current_stage' => IpcBatch::STAGE_FINISHED]);
            }

            // Each draft save closes out one inspection round — its answers now live forever in
            // the revision snapshot above, so the live row is cleared back to blank immediately
            // after, ready for whoever records the next round (whether that's this same session
            // or a fresh page load later). Only the fields that are asked once and carried
            // forward (line leader/coding machine) or re-derived fresh every time regardless of
            // round (standard_weight_mb) survive; a finalized row is left untouched since that's
            // the permanent record shown on the now-read-only page.
            if (! $finalize) {
                $packingCheck->forceFill([
                    ...array_fill_keys(self::checklistFieldKeys(), null),
                    'sum_weight_mb' => null,
                    'remarks' => null,
                    'decision' => null,
                ])->save();
            }

            return $packingCheck->fresh(['revisions.user']);
        });
    }

    /**
     * "Standard weight MB" is the reference master-box weight, and the only numeric master-box
     * reading captured anywhere earlier in the workflow is Start Inspection's BERAT_M.BOX sample
     * set (startup_inspection_samples.weight_master_box). Legacy re-typed this by hand on the
     * Packing form; this port reads the last filled sample instead so QC can't transcribe it
     * wrong. Null when Start Inspection recorded no weights — those samples are optional.
     *
     * Public/static so the controller can show the same value on the form before any save.
     */
    public static function standardWeightMbFor(IpcBatch $batch): ?string
    {
        return $batch->startupInspection?->samples()
            ->whereNotNull('weight_master_box')
            ->orderByDesc('sample_no')
            ->value('weight_master_box');
    }

    /**
     * @return list<string>
     */
    private static function checklistFieldKeys(): array
    {
        return collect(PackingCheck::checklistGroups())
            ->flatMap(fn (array $group) => array_keys($group['fields']))
            ->all();
    }
}
