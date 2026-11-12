<?php

namespace App\Http\Requests;

use App\Models\IpcBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartupAiReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field' => ['required', Rule::in(array_keys(config('vision.startup_ai.fields')))],
            // What the operator typed on the form so far (not saved yet); both optional.
            'mixing_date' => ['nullable', 'date'],
            'exp_date' => ['nullable', 'date'],
            'shelf_life_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            // tube / non_tube as chosen on the form; decides the coding path (the AI only cross-checks it).
            'packaging_type' => ['nullable', Rule::in(IpcBatch::PACKAGING_TYPES)],
        ];
    }
}
