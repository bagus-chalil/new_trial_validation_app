<?php

namespace App\Http\Requests;

use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately matches legacy's Finished Check save button, which has zero required-field
 * validation — it can be submitted completely blank. Every field here is nullable; only the
 * type/shape of a value is checked when one is actually given. See the "Finished Check" note
 * in ipc_app/CLAUDE.md for the reasoning behind not diverging from that legacy behavior here.
 */
class SaveFinishedCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'quantity_wi' => ['nullable', 'numeric', 'min:0'],
            'masterbox' => ['nullable', 'numeric', 'min:0'],
            'no_pallet_qty' => ['nullable', 'numeric', 'min:0'],
            'quantity_sampling_aql' => ['nullable', 'integer', 'min:0'],
            'quantity_sample_aql_cd' => ['nullable', 'integer', 'min:0'],
            'quantity_sample_aql_md' => ['nullable', 'integer', 'min:0'],
            'quantity_sample_aql_mnd' => ['nullable', 'integer', 'min:0'],
            'quantity_special_inspection' => ['nullable', 'integer', 'min:0'],
            'quantity_special_inspection_cd' => ['nullable', 'integer', 'min:0'],
            'quantity_special_inspection_md' => ['nullable', 'integer', 'min:0'],
            'quantity_special_inspection_mnd' => ['nullable', 'integer', 'min:0'],
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'disposition' => ['nullable', 'in:'.implode(',', FinishedCheck::DISPOSITIONS)],
            'remarks' => ['nullable', 'string'],
            'samples' => ['nullable', 'array'],
        ];

        foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
            $rules["samples.{$key}.ac"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.cd"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.md"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.mnd"] = ['nullable', 'integer', 'min:0'];
            $rules["samples.{$key}.remark"] = ['nullable', 'string'];
        }

        return $rules;
    }
}
