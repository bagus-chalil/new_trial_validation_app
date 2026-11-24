<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesDecimalComma;
use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcBatch;
use App\Models\IpcRoundCorrection;
use App\Models\PackingCheck;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Corrects one saved TH Progress round an approver flagged. The round is already a completed
 * record, so every field it needs on a normal "Selesaikan" is required here too — a correction
 * must leave a round that is as complete as a final save, plus the reason for the change.
 */
class SaveRoundCorrectionRequest extends FormRequest
{
    use NormalizesDecimalComma;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->correction()?->stage === IpcBatch::STAGE_FILLING) {
            $this->normalizeDecimalCommas(['samples.*.weight_value']);
        }
    }

    public function correction(): ?IpcRoundCorrection
    {
        $correction = $this->route('correction');

        return $correction instanceof IpcRoundCorrection ? $correction : null;
    }

    public function messages(): array
    {
        return [
            'correction_reason.required' => 'Tuliskan apa yang dikoreksi dan alasannya, supaya tercatat di audit.',
            'masterbox.regex' => 'Masterbox harus berupa angka dipisah koma, contoh: 4,17,55,78.',
            'no_pallet_qty.regex' => 'No. Pallet & Qty harus berformat pallet/qty, contoh: 1/2016 (beberapa dipisah koma).',
        ];
    }

    public function rules(): array
    {
        $rules = ['correction_reason' => ['required', 'string', 'max:2000']];

        return $rules + match ($this->correction()?->stage) {
            IpcBatch::STAGE_FILLING => [
                'sample_bulk_odor_status' => ['required', 'in:Conform,Not Conform'],
                'sample_leakage_test_status' => ['required', 'in:Conform,Not Conform,N/A'],
                'remarks' => ['required', 'string'],
                'decision' => ['required', 'in:'.implode(',', FillingCheck::DECISIONS)],
                'samples' => ['required', 'array', 'min:1'],
                'samples.*.sample_no' => ['required', 'integer', 'min:1'],
                'samples.*.weight_value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            ],
            IpcBatch::STAGE_PACKING => $this->packingRules(),
            IpcBatch::STAGE_FINISHED => $this->finishedRules(),
            default => [],
        };
    }

    /** @return array<string, list<string>> */
    private function packingRules(): array
    {
        $rules = [
            'sum_weight_mb' => ['required', 'integer', 'min:0'],
            'remarks' => ['required', 'string'],
            'decision' => ['required', 'in:'.implode(',', PackingCheck::DECISIONS)],
        ];

        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = ['required', 'in:'.implode(',', $group['options'])];
            }
        }

        return $rules;
    }

    /** @return array<string, list<string>> */
    private function finishedRules(): array
    {
        $rules = [
            'quantity_wi' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'masterbox' => ['required', 'string', 'max:255', 'regex:/^\d+(,\d+)*$/'],
            'no_pallet_qty' => ['required', 'string', 'max:255', 'regex:/^\d+\/\d+(,\d+\/\d+)*$/'],
            'disposition' => ['required', 'in:'.implode(',', FinishedCheck::DISPOSITIONS)],
            'remarks' => ['required', 'string'],
            'samples' => ['nullable', 'array'],
        ];

        foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
            foreach (['ac', 'cd', 'md', 'mnd'] as $col) {
                $rules["samples.{$key}.{$col}"] = ['nullable', 'integer', 'min:0', 'max:4294967295'];
            }
            $rules["samples.{$key}.remark"] = ['nullable', 'string'];
        }

        return $rules;
    }
}
