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
            $validator->after(fn (Validator $validator) => $this->validateDraft($validator));

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

    /**
     * A draft save is meant to let QC record whatever it has so far — but two things are not
     * "progress": a save with literally nothing filled in (an empty row), and a save that
     * records an assessment (Decision, or a Sample Bulk&Odor/Leakage Conform/Not Conform call)
     * with zero actual weight measurements behind it. Revised 2026-09-04 after direct feedback:
     * a real batch had 3 draft rounds saved with Decision=Passed and 0/10 samples ever filled —
     * technically blocked from finalizing, but a misleading TH_PROGESS history along the way,
     * and downstream Packing Check has nothing real to show for "average weight" if that pattern
     * is ever allowed to continue. Both checks share the same "add one error, bail" shape rather
     * than one combined condition, so the two failure messages stay distinct.
     */
    private function validateDraft(Validator $validator): void
    {
        $hasSampleValue = collect($this->input('samples', []))
            ->contains(fn ($row) => filled($row['weight_value'] ?? null));

        $hasAnyValue = filled($this->input('sample_bulk_odor_status'))
            || filled($this->input('sample_leakage_test_status'))
            || filled($this->input('remarks'))
            || filled($this->input('decision'))
            || $hasSampleValue;

        if (! $hasAnyValue) {
            $validator->errors()->add('progress', 'Isi minimal satu data sebelum menyimpan progress.');

            return;
        }

        $hasAssessment = filled($this->input('decision'))
            || filled($this->input('sample_bulk_odor_status'))
            || filled($this->input('sample_leakage_test_status'));

        if ($hasAssessment && ! $hasSampleValue) {
            $validator->errors()->add('samples', 'Isi minimal satu sample berat sebelum mencatat Decision/Sample Check.');
        }
    }
}
