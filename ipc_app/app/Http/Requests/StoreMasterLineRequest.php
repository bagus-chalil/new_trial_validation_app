<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', Rule::exists('master_lines', 'id')],
            'category' => ['required', 'string', 'max:100'],
            'area' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ];
    }
}
