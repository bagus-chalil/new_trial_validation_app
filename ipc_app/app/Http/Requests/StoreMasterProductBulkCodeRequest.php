<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterProductBulkCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $masterProduct = $this->route('masterProduct');

        return [
            'id' => ['nullable', Rule::exists('master_product_bulk_codes', 'id')->where('master_product_id', $masterProduct->id)],
            'bulk_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('master_product_bulk_codes', 'bulk_code')
                    ->where('master_product_id', $masterProduct->id)
                    ->ignore($this->input('id')),
            ],
            'no_batch' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
