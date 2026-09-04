<?php

namespace App\Http\Requests;

use App\Actions\PackingChecks\SavePackingCheck;
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
        // server-side from the batch's Start Inspection weight-master-box readings, never
        // submitted by the client. Whether it's actually present is checked in withValidator()
        // below instead, since a plain field rule can't reach outside the request body.

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
            return;
        }

        $validator->after(function (Validator $validator) {
            /** @var IpcBatch $batch */
            $batch = $this->route('batch');

            if (SavePackingCheck::standardWeightMbFor($batch) === null) {
                $validator->errors()->add(
                    'standard_weight_mb',
                    'Standard Weight MB belum tersedia — lengkapi data Weight Master Box di Start Inspection terlebih dahulu.',
                );
            }

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
