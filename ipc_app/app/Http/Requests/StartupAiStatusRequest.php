<?php

namespace App\Http\Requests;

use App\Models\IpcBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** What the operator has typed on the Startup form so far (not saved yet); all optional. */
class StartupAiStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mixing_date' => ['nullable', 'date'],
            'exp_date' => ['nullable', 'date'],
            'shelf_life_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'packaging_type' => ['nullable', Rule::in(IpcBatch::PACKAGING_TYPES)],
        ];
    }

    /** @return array{mixing_date: ?string, exp_date: ?string, shelf_life_months: ?int, packaging_type: ?string} */
    public function entered(): array
    {
        return [
            'mixing_date' => $this->validated('mixing_date'),
            'exp_date' => $this->validated('exp_date'),
            'shelf_life_months' => $this->validated('shelf_life_months'),
            'packaging_type' => $this->validated('packaging_type'),
        ];
    }
}
