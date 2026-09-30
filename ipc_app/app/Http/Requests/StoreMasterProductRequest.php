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
            'is_active' => ['boolean'],
        ];
    }
}
