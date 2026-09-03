<?php

namespace App\Http\Requests;

use App\Models\StartupInspectionItem;
use Illuminate\Foundation\Http\FormRequest;

class SaveStartupInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        // All 10 checklist items required, unlike legacy which only enforces 7 of them (very
        // likely a legacy bug — see ipc_app/CLAUDE.md). Deliberate improvement, not a port.
        foreach (StartupInspectionItem::PARAMETER_KEYS as $key) {
            $rules["items.{$key}.status"] = ['required', 'in:'.implode(',', [
                StartupInspectionItem::STATUS_OK,
                StartupInspectionItem::STATUS_PARTIAL_OK,
                StartupInspectionItem::STATUS_NOT_OK,
            ])];
            $rules["items.{$key}.remark"] = ['nullable', 'string'];
        }

        // Volume/Weight and Weight Master Box samples are deliberately optional for now — the
        // user confirmed IPC can't do this weighing yet at this stage (2026-09-03). See
        // ipc_app/CLAUDE.md — don't make these required without checking with the user again.
        $rules['samples'] = ['nullable', 'array'];
        $rules['samples.*.sample_no'] = ['required_with:samples', 'integer', 'between:1,30'];
        $rules['samples.*.volume_weight'] = ['nullable', 'numeric', 'min:0'];
        $rules['samples.*.weight_master_box'] = ['nullable', 'numeric', 'min:0'];

        $rules['test_results'] = ['nullable', 'array'];
        $rules['test_results.*.is_performed'] = ['nullable', 'boolean'];
        $rules['test_results.*.remark'] = ['nullable', 'string'];

        return $rules;
    }
}
