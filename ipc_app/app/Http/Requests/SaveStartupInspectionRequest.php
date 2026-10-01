<?php

namespace App\Http\Requests;

use App\Models\StartupInspectionItem;
use App\Models\StartupInspectionSample;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

        // Volume/Weight is required for all 30 samples (user, 2026-10-01). Weight Master Box
        // stays optional — IPC still can't weigh master boxes at this stage (2026-09-03).
        $rules['samples'] = ['nullable', 'array'];
        $rules['samples.*.sample_no'] = ['required_with:samples', 'integer', 'between:1,30'];
        $rules['samples.*.volume_weight'] = ['nullable', 'numeric', 'min:0'];
        $rules['samples.*.weight_master_box'] = ['nullable', 'numeric', 'min:0'];

        $rules['test_results'] = ['nullable', 'array'];
        $rules['test_results.*.is_performed'] = ['nullable', 'boolean'];
        $rules['test_results.*.remark'] = ['nullable', 'string'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = collect($this->input('samples', []))
                ->keyBy(fn ($row) => (int) ($row['sample_no'] ?? 0));

            for ($sampleNo = 1; $sampleNo <= StartupInspectionSample::SAMPLE_COUNT; $sampleNo++) {
                if (blank($rows->get($sampleNo)['volume_weight'] ?? null)) {
                    $validator->errors()->add('samples', 'Isi seluruh '.StartupInspectionSample::SAMPLE_COUNT.' sample Volume / Weight.');
                    break;
                }
            }
        });
    }
}
