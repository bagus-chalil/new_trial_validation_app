<?php

namespace App\Http\Requests;

use App\Models\IpcBatch;
use App\Models\StartupCheck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStartupCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function isPackingFgFlow(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof IpcBatch && $batch->flow_type === IpcBatch::FLOW_PACKING_FG;
    }

    public function rules(): array
    {
        $rules = [
            'mixing_date' => ['required', 'date'],
            'exp_date' => ['required', 'date'],
            'master_line_id' => ['required', Rule::exists('master_lines', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'validation_report_status' => ['required', 'in:'.implode(',', StartupCheck::VALIDATION_REPORT_OPTIONS)],
            'filling_range_min' => ['nullable', 'numeric', 'decimal:0,2'],
            'filling_range_max' => ['nullable', 'numeric', 'decimal:0,2'],
            'density' => ['nullable', 'numeric', 'decimal:0,4', 'min:0'],
            'density_not_applicable' => ['nullable', 'boolean'],
            // Confirmed with real IPC users 2026-09-03: SOP no longer does the 30-sample
            // BottleData weighing — this is entered once, directly, matching the legacy
            // screen's own single input box for AVERAGE_OF_EMPTY_BOTTLE_WEIGHT.
            // Not asked on the Packing + FG flow (no filling there), so it can't be required.
            'average_of_empty_bottle_weight' => [$this->isPackingFgFlow() ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'min:0'],
            'heating' => ['nullable', 'string', 'max:255'],
            'coding_format_primary' => ['nullable', 'string', 'max:1000'],
            'coding_format_secondary' => ['nullable', 'string', 'max:1000'],
            'coding_format_tersier' => ['nullable', 'string', 'max:1000'],
            'coding_operator_na_primary' => ['nullable', 'boolean'],
            'coding_operator_na_secondary' => ['nullable', 'boolean'],
            'coding_operator_na_tersier' => ['nullable', 'boolean'],
            'product_appearance_status' => ['required', 'in:'.implode(',', StartupCheck::PRODUCT_CHECK_OPTIONS)],
            'product_leakage_test_status' => ['required', 'in:'.implode(',', StartupCheck::PRODUCT_CHECK_OPTIONS)],
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];

        $batch = $this->route('batch');
        $groups = $batch instanceof IpcBatch ? StartupCheck::checklistGroupsFor($batch) : StartupCheck::checklistGroups();

        foreach ($groups as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = ['required', 'in:'.implode(',', $group['options'])];
            }
        }

        return $rules;
    }
}
