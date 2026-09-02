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
            'filling_range_min' => ['nullable', 'numeric'],
            'filling_range_max' => ['nullable', 'numeric'],
            'density' => ['nullable', 'numeric', 'min:0'],
            'heating' => ['nullable', 'string', 'max:255'],
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'im_number' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'coding' => ['nullable', 'string', 'max:255'],
            'temperature_setting' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'bottle_weights' => ['nullable', 'array'],
            'bottle_weights.*.sample_no' => ['required', 'integer', 'min:1'],
            'bottle_weights.*.weight_value' => ['nullable', 'numeric', 'min:0'],
        ];

        foreach (array_keys(StartupCheck::AVAILABILITY_FIELDS) as $field) {
            $rules[$field] = ['required', 'in:'.StartupCheck::STATUS_AVAILABLE.','.StartupCheck::STATUS_NOT_AVAILABLE];
        }

        foreach (array_keys(StartupCheck::CONFORM_FIELDS) as $field) {
            $rules[$field] = ['required', 'in:'.StartupCheck::STATUS_CONFORM.','.StartupCheck::STATUS_NOT_CONFORM];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasWeight = collect($this->input('bottle_weights', []))
                ->contains(fn ($row) => filled($row['weight_value'] ?? null));

            if (! $hasWeight) {
                $validator->errors()->add('bottle_weights', 'Isi minimal satu berat sample bottle.');
            }
        });
    }
}
