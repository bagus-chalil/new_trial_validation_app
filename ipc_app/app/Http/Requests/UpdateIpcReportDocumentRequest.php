<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIpcReportDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_number' => ['required', 'string', 'max:50'],
            'revision' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
