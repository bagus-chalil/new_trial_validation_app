<?php

namespace App\Http\Requests;

use App\Http\Controllers\FinishedCheckController;
use App\Http\Requests\Concerns\ValidatesThProgressRound;
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
 * The 19-group/76-field AQL sample grid is NOT required, cell-by-cell or row-by-row — a real
 * sample (e.g. no tersier packaging on this product) legitimately has nothing to record there.
 * A 2026-09-04 revision briefly required at least one of AC/CD/MD/mD per row on finalize, but
 * that was reversed 2026-09-15 per direct user request: an entirely blank AQL row is valid QC
 * data (nothing to report for that parameter), not an error — the report renders it as "N/A"
 * instead of leaving it blank/dashed (see resources/views/pdf/approval-finished.blade.php and
 * the matching approval/finished.tsx + print/finished.tsx pages).
 */
class SaveFinishedCheckRequest extends FormRequest
{
    use ValidatesThProgressRound;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // "4, 17 , 55" is fine to type � spaces are stripped before the format check and storage.
        foreach (['masterbox', 'no_pallet_qty'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/\s+/', '', $this->input($field))]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'masterbox.regex' => 'Masterbox harus berupa angka dipisah koma, contoh: 4,17,55,78.',
            'no_pallet_qty.regex' => 'No. Pallet & Qty harus berformat pallet/qty, contoh: 1/2016 (beberapa dipisah koma).',
        ];
    }

    public function rules(): array
    {
        $required = $this->boolean('finalize') ? 'required' : 'nullable';

        // Header quantities are wajib on every save, draft included (user, 2026-10-01);
        // disposition/remarks/photos stay finalize-only.
        $rules = [
            'finalize' => ['nullable', 'boolean'],
            // max:9999999999.99 matches the finished_checks.quantity_wi decimal(12,2) column precision — without this, a value with more than 10 integer
            // digits passes validation but then crashes with a raw SQL "out of range" error
            // instead of a clean, visible validation message.
            'quantity_wi' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            // Free-text lists (columns are strings): masterbox "4,17,55,78", no_pallet_qty
            // "1/2016" or several pairs "1/2016,2/1800" (pallet number / quantity).
            'masterbox' => ['required', 'string', 'max:255', 'regex:/^\d+(,\d+)*$/'],
            'no_pallet_qty' => ['required', 'string', 'max:255', 'regex:/^\d+\/\d+(,\d+\/\d+)*$/'],
            // max:4294967295 matches the unsignedInteger column type of every AQL quantity field
            // below (finished_checks + finished_check_samples) — same crash class as the decimal
            // fields above: 'integer'/'min:0' alone lets an out-of-range value reach a raw SQL
            // "out of range" error instead of a clean validation message.
            'quantity_sampling_aql' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity_sample_aql_cd' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity_sample_aql_md' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity_sample_aql_mnd' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity_special_inspection' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity_special_inspection_cd' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity_special_inspection_md' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity_special_inspection_mnd' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'disposition' => [$required, 'in:'.implode(',', FinishedCheck::DISPOSITIONS)],
            'remarks' => [$required, 'string'],
            'samples' => ['nullable', 'array'],
        ];

        foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
            $rules["samples.{$key}.ac"] = ['nullable', 'integer', 'min:0', 'max:4294967295'];
            $rules["samples.{$key}.cd"] = ['nullable', 'integer', 'min:0', 'max:4294967295'];
            $rules["samples.{$key}.md"] = ['nullable', 'integer', 'min:0', 'max:4294967295'];
            $rules["samples.{$key}.mnd"] = ['nullable', 'integer', 'min:0', 'max:4294967295'];
            $rules["samples.{$key}.remark"] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var IpcBatch $batch */
            $batch = $this->route('batch');
            $this->validateThProgressRound(
                $validator,
                (int) $batch->finishedCheck?->save_count,
                $this->boolean('finalize'),
                'Packing Check',
                (bool) $batch->packingCheck?->completed_at,
            );
        });

        if (! $this->boolean('finalize')) {
            $validator->after(fn (Validator $validator) => $this->validateDraftIsNotCompletelyEmpty($validator));

            return;
        }

        $validator->after(function (Validator $validator) {
            $this->validateFinalizePhotos($validator);
        });
    }

    /**
     * A draft save is meant to let QC record whatever it has so far — but a save with literally
     * nothing filled in is not "progress," it's an empty row. Block that, same intent as the
     * finalize-required checks below, just a much lower bar.
     */
    private function validateDraftIsNotCompletelyEmpty(Validator $validator): void
    {
        $headerFields = [
            'quantity_wi', 'masterbox', 'no_pallet_qty',
            'quantity_sampling_aql', 'quantity_sample_aql_cd', 'quantity_sample_aql_md', 'quantity_sample_aql_mnd',
            'quantity_special_inspection', 'quantity_special_inspection_cd', 'quantity_special_inspection_md', 'quantity_special_inspection_mnd',
            'disposition', 'remarks',
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
    }

    private function validateFinalizePhotos(Validator $validator): void
    {
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
    }
}
