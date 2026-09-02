<?php

namespace App\Http\Requests;

use App\Models\FillingCheck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveFillingCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sample_bulk_odor_status' => ['required', 'in:Conform,Not Conform'],
            'sample_leakage_test_status' => ['required', 'in:Conform,Not Conform'],
            'standard_weight_and_volume' => ['required', 'string', 'max:255'],
            'line_leader_name' => ['required', 'string', 'max:255'],
            'remarks' => ['required', 'string'],
            'decision' => ['required', 'in:'.implode(',', FillingCheck::DECISIONS)],
            'samples' => ['nullable', 'array'],
            'samples.*.sample_no' => ['required', 'integer', 'min:1'],
            'samples.*.weight_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Legacy's Filling_Check save button requires all 10 weight samples non-blank
            // (Controls/625.json's Button4_5 OnSelect IsBlank checks).
            $rows = collect($this->input('samples', []))
                ->keyBy(fn ($row) => (int) ($row['sample_no'] ?? 0));

            for ($sampleNo = 1; $sampleNo <= 10; $sampleNo++) {
                if (blank($rows->get($sampleNo)['weight_value'] ?? null)) {
                    $validator->errors()->add('samples', 'Isi seluruh 10 sample berat filling.');
                    break;
                }
            }
        });
    }
}
