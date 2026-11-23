<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', Rule::exists('master_products', 'id')],
            'fg_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('master_products', 'fg_code')->ignore($this->input('id')),
            ],
            'product_name' => ['required', 'string', 'max:150'],
            // Feeds Vision OCR's EXP = MFD + shelf-life check; blank = not known yet.
            'shelf_life_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            // Vision Service coding formula; blank = "auto". The full syntax is validated by the
            // Vision Service (HTTP 400 FORMULA_INVALID -> REVIEW); here only a JSON array is checked.
            'coding_formula' => [
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $value = trim((string) $value);
                    if (! str_starts_with($value, '[')) {
                        return;
                    }
                    $decoded = json_decode($value, true);
                    if (! is_array($decoded) || $decoded === [] || ! array_is_list($decoded) || collect($decoded)->contains(fn ($s) => ! is_string($s) || trim($s) === '')) {
                        $fail('Formula coding berbentuk [..] harus JSON array berisi teks, mis. ["{BATCH3}","EXP {MMM} {YY}"].');
                    }
                },
            ],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('coding_formula')) {
            $formula = trim((string) $this->input('coding_formula'));
            $this->merge(['coding_formula' => $formula === '' || strtolower($formula) === 'auto' ? null : $formula]);
        }
    }
}
