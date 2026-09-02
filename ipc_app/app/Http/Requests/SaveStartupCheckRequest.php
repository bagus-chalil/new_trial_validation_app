<?php

namespace App\Http\Requests;

use App\Models\StartupCheck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveStartupCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'validation_report_status' => ['required', 'string', 'max:255'],
            'filling_range_min' => ['nullable', 'numeric'],
            'filling_range_max' => ['nullable', 'numeric'],
            'density' => ['nullable', 'numeric', 'min:0'],
            'heating' => ['nullable', 'string', 'max:255'],
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'bottle_weights' => ['nullable', 'array'],
            'bottle_weights.*.sample_no' => ['required', 'integer', 'min:1'],
            'bottle_weights.*.weight_value' => ['nullable', 'numeric', 'min:0'],
        ];

        foreach (StartupCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = ['required', 'in:'.implode(',', $group['options'])];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Legacy's BottleData screen requires all 30 samples non-blank, not just one.
            $rows = collect($this->input('bottle_weights', []))
                ->keyBy(fn ($row) => (int) ($row['sample_no'] ?? 0));

            for ($sampleNo = 1; $sampleNo <= 30; $sampleNo++) {
                if (blank($rows->get($sampleNo)['weight_value'] ?? null)) {
                    $validator->errors()->add('bottle_weights', 'Isi seluruh 30 sample berat bottle.');
                    break;
                }
            }
        });
    }
}
