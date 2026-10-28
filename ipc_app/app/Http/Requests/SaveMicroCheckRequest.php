<?php

namespace App\Http\Requests;

use App\Models\MicroCheck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * "Simpan Progress" accepts a partially filled block (at least one Result); "Selesaikan"
 * (`finalize`) needs every result and Std (cfu/gr), locks the record and sends the batch to
 * Approval. Values are free text because the paper holds entries like "<10", "<100" and "(-)".
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
        $presence = $this->boolean('finalize') ? 'required' : 'nullable';

        return [
            'finalize' => ['sometimes', 'boolean'],
            ...collect(MicroCheck::valueColumns())
                ->mapWithKeys(fn (string $column) => [$column => [$presence, 'string', 'max:50']])
                ->all(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->boolean('finalize')) {
            return;
        }

        // Std comes pre-filled, so only the Results count as "something to save".
        $validator->after(function (Validator $validator) {
            $hasResult = collect(array_keys(MicroCheck::PARAMETERS))->contains(fn (string $key) => filled($this->input("{$key}_result")));

            if (! $hasResult) {
                $validator->errors()->add('progress', 'Isi minimal satu Result sebelum menyimpan progress.');
            }
        });
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
