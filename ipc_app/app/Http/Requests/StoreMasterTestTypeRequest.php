<?php

namespace App\Http\Requests;

use App\Models\MasterTestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterTestTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', Rule::exists('master_test_types', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(MasterTestType::CATEGORIES)],
            'is_active' => ['boolean'],
        ];
    }
}
