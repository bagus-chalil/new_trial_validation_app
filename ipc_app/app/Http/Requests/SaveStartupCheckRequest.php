<?php

namespace App\Http\Requests;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\StartupCheck;
use App\Services\Vision\ExpDateCalculator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStartupCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function isPackingFgFlow(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof IpcBatch && $batch->flow_type === IpcBatch::FLOW_PACKING_FG;
    }

    /**
     * EXP date is calculated by the system (mixing date + the product's shelf-life from Master
     * Produk), so the form keeps working when the IM photo / AI is unavailable and OCR is only a
     * cross-check, never the source. The submitted value is overridden when it can be derived;
     * otherwise (no shelf-life on the product, or the day does not exist in the target month)
     * the operator's own exp_date is required and kept.
     */
    protected function prepareForValidation(): void
    {
        $batch = $this->route('batch');
        if (! $batch instanceof IpcBatch) {
            return;
        }

        $computed = app(ExpDateCalculator::class)->expDateFromMixing(
            (string) $this->input('mixing_date'),
            $batch->masterProduct?->shelf_life_months,
        );

        if ($computed !== null) {
            $this->merge(['exp_date' => $computed]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'mixing_date' => ['required', 'date'],
            'exp_date' => ['required', 'date'],
            'master_line_id' => ['required', Rule::exists('master_lines', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'validation_report_status' => ['required', 'in:'.implode(',', StartupCheck::VALIDATION_REPORT_OPTIONS)],
            'filling_range_min' => ['nullable', 'numeric', 'decimal:0,2'],
            'filling_range_max' => ['nullable', 'numeric', 'decimal:0,2'],
            'density' => ['nullable', 'numeric', 'decimal:0,4', 'min:0'],
            'density_not_applicable' => ['nullable', 'boolean'],
            // Confirmed with real IPC users 2026-09-03: SOP no longer does the 30-sample
            // BottleData weighing — this is entered once, directly, matching the legacy
            // screen's own single input box for AVERAGE_OF_EMPTY_BOTTLE_WEIGHT.
            // Not asked on the Packing + FG flow (no filling there), so it can't be required.
            'average_of_empty_bottle_weight' => [$this->isPackingFgFlow() ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'min:0'],
            'heating' => ['nullable', 'string', 'max:255'],
            // Required: an empty tier must be marked with the N/A button (stored as the literal 'N/A').
            'coding_format_primary' => ['required', 'string', 'max:1000'],
            'coding_format_secondary' => ['required', 'string', 'max:1000'],
            'coding_format_tersier' => ['required', 'string', 'max:1000'],
            'coding_operator_na_primary' => ['nullable', 'boolean'],
            'coding_operator_na_secondary' => ['nullable', 'boolean'],
            'coding_operator_na_tersier' => ['nullable', 'boolean'],
            'product_appearance_status' => ['required', 'in:'.implode(',', StartupCheck::PRODUCT_CHECK_OPTIONS)],
            'product_leakage_test_status' => ['required', 'in:'.implode(',', StartupCheck::PRODUCT_CHECK_OPTIONS)],
            'line_leader_name' => ['nullable', 'string', 'max:255'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];

        $batch = $this->route('batch');
        $groups = $batch instanceof IpcBatch ? StartupCheck::checklistGroupsFor($batch) : StartupCheck::checklistGroups();

        foreach ($groups as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $rules[$field] = ['required', 'in:'.implode(',', $group['options'])];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $batch = $this->route('batch');
            if (! $batch instanceof IpcBatch) {
                return;
            }

            $uploaded = IpcAttachment::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('stage', 'startup')
                ->whereIn('field_label', ['coding_primary', 'coding_secondary', 'coding_tersier'])
                ->pluck('field_label');

            // Label Bulk photo moved here from Filling Check (2026-11-12): always required.
            if (! IpcAttachment::query()->where('ipc_batch_id', $batch->id)->where('stage', 'startup')->where('field_label', 'date_bulk')->exists()) {
                $validator->errors()->add('photo_date_bulk', 'Foto Label Bulk wajib diunggah.');
            }

            foreach (['primary', 'secondary', 'tersier'] as $tier) {
                if (! $this->boolean("coding_operator_na_{$tier}") && ! $uploaded->contains("coding_{$tier}")) {
                    $validator->errors()->add("photo_coding_{$tier}", 'Foto wajib diunggah, atau pilih N/A.');
                }
            }
        });
    }
}
