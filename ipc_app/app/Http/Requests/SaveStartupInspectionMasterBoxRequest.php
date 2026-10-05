<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveStartupInspectionMasterBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'samples' => ['required', 'array'],
            'samples.*.sample_no' => ['required', 'integer', 'between:1,30'],
            'samples.*.weight_master_box' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // This is a one-time fill (locked once any value is saved), so an all-blank submit
        // would be a no-op at best — reject it instead of silently doing nothing.
        $validator->after(function (Validator $validator) {
            $anyFilled = collect($this->input('samples', []))
                ->contains(fn ($row) => ! blank($row['weight_master_box'] ?? null));

            if (! $anyFilled) {
                $validator->errors()->add('samples', 'Isi minimal satu sample Weight Master Box.');
            }
        });
    }
}
