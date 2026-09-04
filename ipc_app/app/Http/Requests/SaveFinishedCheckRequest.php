<?php

namespace App\Http\Requests;

use App\Http\Controllers\FinishedCheckController;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Draft saves (finalize=false) let every individual field go blank — QC can record one round
 * and come back later — but the save as a whole must not be completely empty (see
 * withValidator()), same bar as Packing/Filling Check. SAVE & END (finalize=true) requires the
 * header quantities, line leader, disposition, remarks, and all three photos — revised
 * 2026-09-04 after direct live-testing feedback showed a blank form could be finalized straight
 * through, which isn't acceptable even though the raw legacy export itself has zero server-side
 * validation here (see ipc_app/CLAUDE.md's "Finished Check" note).
 * The 19-group/76-field AQL sample grid is deliberately left nullable even on finalize — the
 * count of individually-required numeric cells would be unrealistic to enforce and isn't what
 * broke for the user; only the header-level fields need to actually block finalize.
 */
class SaveFinishedCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->boolean('finalize') ? 'required' : 'nullable';

        $rules = [
            'finalize' => ['nullable', 'boolean'],
            'quantity_wi' => [$required, 'numeric', 'min:0'],
            'masterbox' => [$required, 'numeric', 'min:0'],
            'no_pallet_qty' => [$required, 'numeric', 'min:0'],
            'quantity_sampling_aql' => [$required, 'integer', 'min:0'],
            'quantity_sample_aql_cd' => [$required, 'integer', 'min:0'],
            'quantity_sample_aql_md' => [$required, 'integer', 'min:0'],
            'quantity_sample_aql_mnd' => [$required, 'integer', 'min:0'],
            'quantity_special_inspection' => [$required, 'integer', 'min:0'],
            'quantity_special_inspection_cd' => [$required, 'integer', 'min:0'],
            'quantity_special_inspection_md' => [$required, 'integer', 'min:0'],
            'quantity_special_inspection_mnd' => [$required, 'integer', 'min:0'],
            'line_leader_name' => [$required, 'string', 'max:255'],
            'disposition' => [$required, 'in:'.implode(',', FinishedCheck::DISPOSITIONS)],
            'remarks' => [$required, 'string'],
            'samples' => ['nullable', 'array'],
        ];

        foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
            $rules["samples.{$key}.ac"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.cd"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.md"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.mnd"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.remark"] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->boolean('finalize')) {
            $validator->after(function (Validator $validator) {
                // A draft save is meant to let QC record whatever it has so far — but a save with
                // literally nothing filled in is not "progress," it's an empty row. Block that,
                // same intent as the finalize-required checks below, just a much lower bar.
                $headerFields = [
                    'quantity_wi', 'masterbox', 'no_pallet_qty',
                    'quantity_sampling_aql', 'quantity_sample_aql_cd', 'quantity_sample_aql_md', 'quantity_sample_aql_mnd',
                    'quantity_special_inspection', 'quantity_special_inspection_cd', 'quantity_special_inspection_md', 'quantity_special_inspection_mnd',
                    'line_leader_name', 'disposition', 'remarks',
                ];

                $hasHeaderValue = collect($headerFields)->contains(fn ($field) => filled($this->input($field)));

                $hasSampleValue = collect($this->input('samples', []))
                    ->contains(fn ($row) => filled($row['ac'] ?? null)
                        || filled($row['cd'] ?? null)
                        || filled($row['md'] ?? null)
                        || filled($row['mnd'] ?? null)
                        || filled($row['remark'] ?? null));

                if (! $hasHeaderValue && ! $hasSampleValue) {
                    $validator->errors()->add('progress', 'Isi minimal satu data sebelum menyimpan progress.');
                }
            });

            return;
        }

        $validator->after(function (Validator $validator) {
            /** @var IpcBatch $batch */
            $batch = $this->route('batch');

            $uploadedPhotoFields = IpcAttachment::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('stage', 'finished')
                ->whereIn('field_label', FinishedCheckController::PHOTO_FIELDS)
                ->pluck('field_label');

            foreach (FinishedCheckController::PHOTO_FIELDS as $field) {
                if (! $uploadedPhotoFields->contains($field)) {
                    $validator->errors()->add("photo_{$field}", 'Foto wajib diunggah.');
                }
            }
        });
    }
}
