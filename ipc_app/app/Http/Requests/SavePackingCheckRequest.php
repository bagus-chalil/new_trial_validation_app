<?php

namespace App\Http\Requests;

use App\Models\PackingCheck;
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

        $rules = [
            'finalize' => ['required', 'boolean'],
            'sum_weight_mb' => ['nullable', 'numeric', 'min:0'],
            // Captured once on the first round and carried forward by SavePackingCheck after
            // that, so they stay optional here and the form stops asking on later rounds.
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'coding_machine' => ['nullable', 'string', 'max:255'],
            'remarks' => [$required, 'string'],
            'decision' => [$required, 'in:'.implode(',', PackingCheck::DECISIONS)],
        ];

        // standard_weight_mb is deliberately absent: it's derived server-side from the batch's
        // Start Inspection weight-master-box readings, never submitted by the client.

        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = [$required, 'in:'.implode(',', $group['options'])];
            }
        }

        return $rules;
    }
}
