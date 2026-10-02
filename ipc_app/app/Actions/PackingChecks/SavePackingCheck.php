<?php

namespace App\Actions\PackingChecks;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevision;
use App\Models\PackingCheckRevisionPhoto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SavePackingCheck
{
    /**
     * Duplicated from PackingCheckController::PHOTO_FIELDS (not imported) — same precedent as
     * BuildsIpcReportPayloads::PHOTOS_BY_STAGE, to avoid an action-layer dependency on a
     * controller class.
     *
     * @var list<string>
     */
    private const PHOTO_FIELDS = ['palletisasi', 'color', 'primary_coding_batch_exp', 'tersier_coding_batch', 'secondary_coding_batch_exp'];

    public function handle(IpcBatch $batch, User $user, array $data): PackingCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $finalize = (bool) ($data['finalize'] ?? false);
            $existing = $batch->packingCheck;
            $fields = collect($data)->except(['finalize', 'standard_weight_mb', 'line_leader_name', 'coding_machine', 'weighing_data'])->all();

            // Asked once on the first round, then carried forward untouched — Std Bruto MB, the
            // coding machine, line leader and Data Timbang don't change between inspection rounds
            // of the same batch, so the form locks them from TH_PROGRESS 2 on and submits nothing
            // for them. A first round that left one blank can still fill it in later.
            $standardWeightMb = $existing?->standard_weight_mb ?? ($data['standard_weight_mb'] ?? null);
            $lineLeaderName = $existing?->line_leader_name ?? ($data['line_leader_name'] ?? null);
            $codingMachine = $existing?->coding_machine ?? ($data['coding_machine'] ?? null);
            $weighingData = $existing?->weighing_data ?? ($data['weighing_data'] ?? null);

            $saveCount = ($existing?->save_count ?? 0) + 1;

            $packingCheck = PackingCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$fields,
                    'standard_weight_mb' => $standardWeightMb,
                    'line_leader_name' => $lineLeaderName,
                    'coding_machine' => $codingMachine,
                    'weighing_data' => $weighingData,
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
            $revision = PackingCheckRevision::create([
                ...collect($packingCheck->getAttributes())
                    ->only((new PackingCheckRevision)->getFillable())
                    ->all(),
                'packing_check_id' => $packingCheck->id,
                'revision_no' => $saveCount,
                'finalize' => $finalize,
                'user_id' => $user->id,
            ]);

            // Snapshot whichever photo is current for each field right now — ipc_attachments
            // accumulates one row per upload for this stage (see
            // PackingCheckController::uploadPhoto()), so "current" is simply the latest row per
            // field. This is what lets the printed report show a distinct photo per TH_PROGRESS
            // round instead of always showing whatever the latest upload happens to be.
            $currentPhotos = IpcAttachment::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('stage', 'packing')
                ->whereIn('field_label', self::PHOTO_FIELDS)
                ->orderBy('id')
                ->get()
                ->keyBy('field_label');

            foreach (self::PHOTO_FIELDS as $field) {
                if ($currentPhotos->has($field)) {
                    PackingCheckRevisionPhoto::create([
                        'packing_check_revision_id' => $revision->id,
                        'field_label' => $field,
                        'ipc_attachment_id' => $currentPhotos[$field]->id,
                        'file_path' => $currentPhotos[$field]->file_path,
                    ]);
                }
            }

            if ($finalize && $batch->current_stage === IpcBatch::STAGE_PACKING) {
                $batch->update(['current_stage' => IpcBatch::STAGE_FINISHED]);
            }

            // Each draft save closes out one inspection round — its answers now live forever in
            // the revision snapshot above, so the live row is cleared back to blank immediately
            // after, ready for whoever records the next round (whether that's this same session
            // or a fresh page load later). Only the fields that are asked once and carried
            // forward (Std Bruto MB/line leader/coding machine/Data Timbang) survive; a finalized
            // row is left untouched since that's the permanent record shown on the now-read-only page.
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
     * @return list<string>
     */
    private static function checklistFieldKeys(): array
    {
        return collect(PackingCheck::checklistGroups())
            ->flatMap(fn (array $group) => array_keys($group['fields']))
            ->all();
    }
}
