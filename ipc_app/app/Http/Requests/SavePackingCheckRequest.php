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
        $rules = [
            'standard_weight_mb' => ['nullable', 'numeric', 'min:0'],
            'sum_weight_mb' => ['nullable', 'numeric', 'min:0'],
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'coding_machine' => ['nullable', 'string', 'max:255'],
            'remarks' => ['required', 'string'],
            'decision' => ['required', 'in:'.implode(',', PackingCheck::DECISIONS)],
        ];

        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = ['required', 'in:'.implode(',', $group['options'])];
            }
        }

        return $rules;
    }
}
