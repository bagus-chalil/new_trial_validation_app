<?php

namespace App\Http\Requests;

use App\Http\Controllers\PackingCheckController;
use App\Http\Requests\Concerns\ValidatesThProgressRound;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\PackingCheck;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePackingCheckRequest extends FormRequest
{
    use ValidatesThProgressRound;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // "1920 - 2000" is fine to type — spaces are stripped before the format check and storage.
        if (is_string($this->input('standard_weight_mb'))) {
            $this->merge(['standard_weight_mb' => preg_replace('/\s+/', '', $this->input('standard_weight_mb'))]);
        }
    }

    public function messages(): array
    {
        return [
            'standard_weight_mb.regex' => 'Std Bruto MB harus berupa angka atau rentang, contoh: 1920-2000.',
        ];
    }

    public function rules(): array
    {
        // Draft saves (finalize=false) let QC record one inspection round and come back later in
        // the shift with the next one, so nothing is required in between — only "Simpan &
        // Selesaikan" enforces the full checklist this request originally always demanded.
        // Same split as SaveFillingCheckRequest.
        $required = $this->boolean('finalize') ? 'required' : 'nullable';

        // Captured once on the first round and carried forward untouched by SavePackingCheck
        // after that (see its handle() for the lock rule) — so they're only wajib on the round
        // that actually sets them. Once a value already exists, the field is locked/disabled on
        // the frontend and stops being resubmitted, so requiring it again would incorrectly
        // block every later round's save.
        /** @var IpcBatch $batch */
        $batch = $this->route('batch');
        $existing = $batch->packingCheck;
        $standardWeightMbRequired = $existing?->standard_weight_mb !== null ? 'nullable' : $required;
        $lineLeaderRequired = $existing?->line_leader_name ? 'nullable' : $required;
        $codingMachineRequired = $existing?->coding_machine ? 'nullable' : $required;
        $weighingDataRequired = $existing?->weighing_data ? 'nullable' : $required;

        $rules = [
            'finalize' => ['required', 'boolean'],
            // Packing line — prefilled with the batch's (Filling) line, changeable on any round
            // until Packing is finalized, since packing can run on another line (user, 2026-10-05).
            // Omitted = keep the current one / fall back to the batch line (see SavePackingCheck).
            'master_line_id' => ['nullable', 'integer', Rule::exists('master_lines', 'id')->where('is_active', true)->whereNull('deleted_at')],
            // "Weight of MB" (sum_weight_mb column): weighed fresh every TH_PROGRESS round, so
            // it's wajib on every save — draft or final — not just on Selesaikan (user, 2026-10-01).
            // Whole numbers only (user, 2026-10-06) — same treatment as Weight Master Box.
            'sum_weight_mb' => ['required', 'integer', 'min:0'],
            // "Std Bruto MB": typed by QC once (user, 2026-10-02), no longer derived from Start Inspection.
            // Usually a range, "1920-2000"; a single value is fine too. Spaces are stripped first.
            'standard_weight_mb' => [$standardWeightMbRequired, 'string', 'max:50', 'regex:/^\d+([.,]\d+)?(-\d+([.,]\d+)?)?$/'],
            'line_leader_name' => [$lineLeaderRequired, 'string', 'max:255'],
            'coding_machine' => [$codingMachineRequired, 'string', 'max:255'],
            'weighing_data' => [$weighingDataRequired, 'in:'.implode(',', PackingCheck::WEIGHING_DATA_OPTIONS)],
            'remarks' => [$required, 'string'],
            'decision' => [$required, 'in:'.implode(',', PackingCheck::DECISIONS)],
        ];

        // The 17 checklist items are wajib on every save, draft included (user, 2026-10-01).
        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = ['required', 'in:'.implode(',', $group['options'])];
            }
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
                (int) $batch->packingCheck?->save_count,
                $this->boolean('finalize'),
                'Filling Check',
                (bool) $batch->fillingCheck?->completed_at,
            );
        });

        if (! $this->boolean('finalize')) {
            $validator->after(function (Validator $validator) {
                // A draft save is meant to let QC record whatever it has so far — but a save with
                // literally nothing filled in is not "progress," it's an empty row. Block that,
                // same intent as the finalize-required checks below, just a much lower bar.
                $checklistFields = collect(PackingCheck::checklistGroups())
                    ->flatMap(fn ($group) => array_keys($group['fields']));

                $hasAnyValue = filled($this->input('sum_weight_mb'))
                    || filled($this->input('standard_weight_mb'))
                    || filled($this->input('line_leader_name'))
                    || filled($this->input('coding_machine'))
                    || filled($this->input('weighing_data'))
                    || filled($this->input('remarks'))
                    || filled($this->input('decision'))
                    || $checklistFields->contains(fn ($field) => filled($this->input($field)));

                if (! $hasAnyValue) {
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
                ->where('stage', 'packing')
                ->whereIn('field_label', PackingCheckController::PHOTO_FIELDS)
                ->pluck('field_label');

            foreach (PackingCheckController::PHOTO_FIELDS as $field) {
                if (! $uploadedPhotoFields->contains($field)) {
                    $validator->errors()->add("photo_{$field}", 'Foto wajib diunggah.');
                }
            }
        });
    }
}
