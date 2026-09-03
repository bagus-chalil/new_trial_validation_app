<?php

namespace App\Http\Requests;

use App\Models\StartupCheck;
use Illuminate\Foundation\Http\FormRequest;

class SaveStartupCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'validation_report_status' => ['required', 'in:'.implode(',', StartupCheck::VALIDATION_REPORT_OPTIONS)],
            'filling_range_min' => ['nullable', 'numeric'],
            'filling_range_max' => ['nullable', 'numeric'],
            'density' => ['nullable', 'numeric', 'min:0'],
            // Confirmed with real IPC users 2026-09-03: SOP no longer does the 30-sample
            // BottleData weighing — this is entered once, directly, matching the legacy
            // screen's own single input box for AVERAGE_OF_EMPTY_BOTTLE_WEIGHT.
            'average_of_empty_bottle_weight' => ['required', 'numeric', 'min:0'],
            'heating' => ['nullable', 'string', 'max:255'],
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];

        foreach (StartupCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = ['required', 'in:'.implode(',', $group['options'])];
            }
        }

        return $rules;
    }
}
