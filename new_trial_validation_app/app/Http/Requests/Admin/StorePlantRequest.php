<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-machines');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $id = $this->integer('id') ?: null;

        return [
            'id' => ['nullable', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:20', Rule::unique('mc_plants', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
