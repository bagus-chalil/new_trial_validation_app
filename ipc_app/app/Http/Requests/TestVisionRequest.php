<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestVisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'field_type' => ['required', Rule::in(config('vision.field_types'))],
            'master_product_id' => ['nullable', 'integer', Rule::exists('master_products', 'id')->whereNull('deleted_at')],
            'exp_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
