<?php

namespace App\Actions\Approvals;

use App\Models\FillingCheckRevision;
use App\Models\FillingCheckSample;
use App\Models\FinishedCheckRevision;
use App\Models\FinishedCheckSample;
use App\Models\IpcBatch;
use App\Models\IpcRoundCorrection;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevision;
use App\Models\User;
use App\Services\AqlSamplingPlan;
use Illuminate\Support\Facades\DB;

/**
 * QC corrects one TH Progress round that an approver flagged ("Minta Revisi" → round picker).
 *
 * The saved round (the `*_revisions` row) is corrected in place so every report keeps showing
 * the round at its own position (TH #2 stays TH #2) — but nothing is lost: the full
 * before/after snapshot, who asked, who corrected and why are written to `ipc_round_corrections`
 * in the same transaction. When the corrected round is also the latest one, the live check row
 * (what the edit form pre-fills) is brought in line, so finalizing afterwards cannot resurrect
 * the old values.
 */
class CorrectCheckRound
{
    /**
     * Full copy of a saved round (header fields + its samples), for the audit before/after.
     *
     * @return array<string, mixed>|null
     */
    public static function snapshot(IpcBatch $batch, string $stage, int $revisionNo): ?array
    {
        $revision = self::revision($batch, $stage, $revisionNo);

        if (! $revision) {
            return null;
        }

        // attributesToArray() (not getAttributes()) so the model casts apply: Weight of MB is a
        // whole number, and a raw "7002.0000" would fail the form's own integer rule.
        $header = collect($revision->attributesToArray())
            ->except(['id', 'created_at', 'updated_at', 'filling_check_id', 'packing_check_id', 'finished_check_id'])
            ->all();

        $samples = match ($stage) {
            IpcBatch::STAGE_FILLING => $revision->samples()->orderBy('sample_no')->get(['sample_no', 'weight_value'])->toArray(),
            IpcBatch::STAGE_FINISHED => $revision->samples()->orderBy('id')->get(['parameter_key', 'ac', 'cd', 'md', 'mnd', 'remark'])->toArray(),
            default => [],
        };

        // Packing keeps one photo per field per round (a replaced photo is part of the audit too).
        $photos = $stage === IpcBatch::STAGE_PACKING
            ? $revision->photos()->orderBy('field_label')->pluck('file_path', 'field_label')->all()
            : [];

        return ['revision' => $header, 'samples' => $samples, 'photos' => $photos];
    }

    public static function revision(IpcBatch $batch, string $stage, int $revisionNo): FillingCheckRevision|PackingCheckRevision|FinishedCheckRevision|null
    {
        $check = match ($stage) {
            IpcBatch::STAGE_FILLING => $batch->fillingCheck,
            IpcBatch::STAGE_PACKING => $batch->packingCheck,
            IpcBatch::STAGE_FINISHED => $batch->finishedCheck,
            default => null,
        };

        return $check?->revisions()->where('revision_no', $revisionNo)->first();
    }

    /**
     * @param  array<string, mixed>  $data  already validated for the stage
     */
    public function handle(IpcBatch $batch, User $user, IpcRoundCorrection $correction, array $data, string $reason): void
    {
        DB::transaction(function () use ($batch, $user, $correction, $data, $reason) {
            $stage = $correction->stage;
            $no = (int) $correction->revision_no;
            // A photo replaced earlier on the correction page already stored the true "before".
            $before = $correction->before_data ?? self::snapshot($batch, $stage, $no);

            match ($stage) {
                IpcBatch::STAGE_FILLING => $this->correctFilling($batch, $no, $data),
                IpcBatch::STAGE_PACKING => $this->correctPacking($batch, $no, $data),
                IpcBatch::STAGE_FINISHED => $this->correctFinished($batch, $no, $data),
            };

            $correction->update([
                'status' => IpcRoundCorrection::STATUS_DONE,
                'corrected_by' => $user->id,
                'corrected_at' => now(),
                'correction_reason' => $reason,
                'before_data' => $before,
                'after_data' => self::snapshot($batch->fresh(), $stage, $no),
            ]);
        });
    }

    private function correctFilling(IpcBatch $batch, int $no, array $data): void
    {
        $check = $batch->fillingCheck;
        $revision = $check->revisions()->where('revision_no', $no)->firstOrFail();

        $samples = collect($data['samples'] ?? [])
            ->filter(fn ($row) => filled($row['weight_value'] ?? null))
            ->map(fn ($row) => ['sample_no' => (int) $row['sample_no'], 'weight_value' => round((float) $row['weight_value'], 2)])
            ->values();

        $average = $samples->isNotEmpty() ? round((float) $samples->avg('weight_value'), 2) : 0;

        $header = [
            'sample_bulk_odor_status' => $data['sample_bulk_odor_status'] ?? null,
            'sample_leakage_test_status' => $data['sample_leakage_test_status'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'decision' => $data['decision'] ?? null,
            'average_weight' => $average,
        ];

        $revision->update($header);
        $revision->samples()->delete();
        $revision->samples()->createMany($samples->map(fn ($row) => [...$row, 'weight_result' => null])->all());

        // The latest round is what the (re)opened form shows and what "selesaikan dari riwayat"
        // adopts — keep the live row identical to it.
        if ($no === (int) $check->save_count) {
            $check->forceFill($header)->save();
            $check->samples()->delete();
            FillingCheckSample::insert($samples->map(fn ($row) => [
                ...$row,
                'filling_check_id' => $check->id,
                'weight_result' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }
    }

    private function correctPacking(IpcBatch $batch, int $no, array $data): void
    {
        $check = $batch->packingCheck;
        $revision = $check->revisions()->where('revision_no', $no)->firstOrFail();

        $keys = [
            ...collect(PackingCheck::checklistGroups())->flatMap(fn (array $group) => array_keys($group['fields']))->all(),
            'sum_weight_mb',
            'remarks',
            'decision',
        ];
        $values = collect($data)->only($keys)->all();

        $revision->update($values);

        if ($no === (int) $check->save_count) {
            $check->forceFill($values)->save();
        }
    }

    private function correctFinished(IpcBatch $batch, int $no, array $data): void
    {
        $check = $batch->finishedCheck;
        $revision = $check->revisions()->where('revision_no', $no)->firstOrFail();

        $header = [
            'quantity_wi' => $data['quantity_wi'] ?? null,
            'masterbox' => $data['masterbox'] ?? null,
            'no_pallet_qty' => $data['no_pallet_qty'] ?? null,
            'disposition' => $data['disposition'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ];

        // Same rule as a normal save: the sampling quantities always follow Quantity WI.
        $plan = filled($header['quantity_wi']) ? AqlSamplingPlan::forLotSize((int) $header['quantity_wi']) : null;
        if ($plan) {
            $header = [...$header, ...$plan];
        }

        $revision->update($header);

        $rows = [];
        foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
            $row = $data['samples'][$key] ?? [];
            $rows[$key] = [
                'ac' => $row['ac'] ?? null,
                'cd' => $row['cd'] ?? null,
                'md' => $row['md'] ?? null,
                'mnd' => $row['mnd'] ?? null,
                'remark' => $row['remark'] ?? null,
            ];
        }

        foreach ($rows as $key => $values) {
            $revision->samples()->updateOrCreate(['parameter_key' => $key], $values);
        }

        if ($no === (int) $check->save_count) {
            $check->forceFill($header)->save();
            foreach ($rows as $key => $values) {
                $check->samples()->updateOrCreate(['parameter_key' => $key], $values);
            }
        }
    }
}
