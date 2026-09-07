<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIpcBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_product_id' => ['required', Rule::exists('master_products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'master_product_bulk_code_id' => [
                'required',
                Rule::exists('master_product_bulk_codes', 'id')
                    ->where('master_product_id', $this->input('master_product_id'))
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'master_line_id' => ['required', Rule::exists('master_lines', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }
}
