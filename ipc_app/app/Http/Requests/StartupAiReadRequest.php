<?php

namespace App\Http\Requests;

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
        ];
    }
}
