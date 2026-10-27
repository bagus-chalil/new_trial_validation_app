<?php

namespace App\Http\Requests;

use App\Models\MicroCheck;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Every result and Std (cfu/gr) is required on every save — the paper form's Micro Check block
 * is all-or-nothing, and Finished Good's "Selesaikan" waits on a complete block. Values are
 * free text because the paper holds entries like "<10", "<100" and "(-)".
 */
class SaveMicroCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordMicroCheck', $this->route('batch'));
    }

    protected function prepareForValidation(): void
    {
        foreach (MicroCheck::valueColumns() as $column) {
            if (is_string($this->input($column))) {
                $this->merge([$column => trim($this->input($column))]);
            }
        }
    }

    public function rules(): array
    {
        return collect(MicroCheck::valueColumns())
            ->mapWithKeys(fn (string $column) => [$column => ['required', 'string', 'max:50']])
            ->all();
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (MicroCheck::PARAMETERS as $key => $label) {
            $attributes["{$key}_result"] = "Result {$label}";
            $attributes["{$key}_std"] = "Std {$label}";
        }

        return $attributes;
    }
}
