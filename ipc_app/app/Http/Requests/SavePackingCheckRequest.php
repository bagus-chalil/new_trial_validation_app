<?php

namespace App\Http\Requests;

use App\Http\Controllers\PackingCheckController;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\PackingCheck;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SavePackingCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        $lineLeaderRequired = $existing?->line_leader_name ? 'nullable' : $required;
        $codingMachineRequired = $existing?->coding_machine ? 'nullable' : $required;

        $rules = [
            'finalize' => ['required', 'boolean'],
            'sum_weight_mb' => [$required, 'numeric', 'min:0'],
            'line_leader_name' => [$lineLeaderRequired, 'string', 'max:255'],
            'coding_machine' => [$codingMachineRequired, 'string', 'max:255'],
            'remarks' => [$required, 'string'],
            'decision' => [$required, 'in:'.implode(',', PackingCheck::DECISIONS)],
        ];

        // standard_weight_mb is deliberately absent from the request payload: it's derived
        // server-side from the batch's Start Inspection weight-master-box readings (defaulting
        // to '0' when none exist yet), never submitted by the client and never blocking finalize.

        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = [$required, 'in:'.implode(',', $group['options'])];
            }
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
                $checklistFields = collect(PackingCheck::checklistGroups())
                    ->flatMap(fn ($group) => array_keys($group['fields']));

                $hasAnyValue = filled($this->input('sum_weight_mb'))
                    || filled($this->input('line_leader_name'))
                    || filled($this->input('coding_machine'))
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
