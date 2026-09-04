<?php

namespace App\Http\Requests;

use App\Models\FillingCheck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveFillingCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Draft saves (finalize=false) let QC revisit this screen repeatedly over a shift and
        // leave fields blank in between — only "Save & End" (finalize=true) enforces the
        // legacy all-required behaviour this request originally always applied.
        $required = $this->boolean('finalize') ? 'required' : 'nullable';

        return [
            'finalize' => ['required', 'boolean'],
            'sample_bulk_odor_status' => [$required, 'in:Conform,Not Conform'],
            'sample_leakage_test_status' => [$required, 'in:Conform,Not Conform'],
            'remarks' => [$required, 'string'],
            'decision' => [$required, 'in:'.implode(',', FillingCheck::DECISIONS)],
            'samples' => ['nullable', 'array'],
            'samples.*.sample_no' => ['required', 'integer', 'min:1'],
            'samples.*.weight_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->boolean('finalize')) {
            $validator->after(function (Validator $validator) {
                // A draft save is meant to let QC record whatever it has so far — but a save with
                // literally nothing filled in is not "progress," it's an empty row. Block that,
                // same intent as the finalize-required checks below, just a much lower bar.
                $hasSampleValue = collect($this->input('samples', []))
                    ->contains(fn ($row) => filled($row['weight_value'] ?? null));

                $hasAnyValue = filled($this->input('sample_bulk_odor_status'))
                    || filled($this->input('sample_leakage_test_status'))
                    || filled($this->input('remarks'))
                    || filled($this->input('decision'))
                    || $hasSampleValue;

                if (! $hasAnyValue) {
                    $validator->errors()->add('progress', 'Isi minimal satu data sebelum menyimpan progress.');
                }
            });

            return;
        }

        $validator->after(function (Validator $validator) {
            // Legacy's Filling_Check "Save & End" requires all 10 weight samples non-blank
            // (Controls/625.json's Button4_5 OnSelect IsBlank checks) — draft saves don't.
            $rows = collect($this->input('samples', []))
                ->keyBy(fn ($row) => (int) ($row['sample_no'] ?? 0));

            for ($sampleNo = 1; $sampleNo <= 10; $sampleNo++) {
                if (blank($rows->get($sampleNo)['weight_value'] ?? null)) {
                    $validator->errors()->add('samples', 'Isi seluruh 10 sample berat filling.');
                    break;
                }
            }
        });
    }
}
