<?php

namespace App\Http\Requests\Validation;

use App\Models\ValidationAssessment;
use App\Support\ValidationCaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveValidationAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');

        return $assessment === null
            ? Gate::allows('create', ValidationAssessment::class)
            : Gate::allows('update', ValidationAssessment::query()->findOrFail((int) $assessment));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $masterOption = fn (string $type) => Rule::exists('master_options', 'name')
            ->where('type', $type)
            ->where('is_active', 1);

        return [
            // The Case type is fixed by the number, so it is only set on create
            // (and not at all when completing an existing Case: `case_id`).
            'case_type' => [$this->route('assessment') === null ? 'required_without:case_id' : 'nullable', 'nullable', Rule::in(ValidationCaseType::ALL)],
            // "Lengkapi Assessment": attach the Assessment to a Case that has none yet.
            'case_id' => [
                'nullable',
                'integer',
                Rule::exists('vd_cases', 'id')->whereNull('deleted_at'),
                Rule::unique('vd_assessments', 'case_id')->whereNull('deleted_at'),
            ],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            // A new NPD SKU may not be in the master yet; then the name is required.
            'product_name' => ['nullable', 'required_without:product_id', 'string', 'max:255'],
            'receive_date' => ['required', 'date'],
            'client' => ['required', 'string', 'max:150'],
            'sales_month' => ['nullable', 'date_format:Y-m'],
            'qty_po' => ['nullable', 'integer', 'min:0'],
            'packaging_type' => ['required', 'string', $masterOption('product_type')],
            'validation_scope' => ['required', 'array', 'min:1'],
            'validation_scope.*' => ['string', $masterOption('validation_scope')],
            'recommended_machine' => ['nullable', 'string', 'max:255'],
            'qco_no' => ['nullable', 'string', 'max:80'],
            'qco_link' => ['nullable', 'string', 'max:500', 'url'],
            'pm_na' => ['nullable', 'boolean'],
            'pm_received_date' => ['nullable', 'date'],
            'bulk_na' => ['nullable', 'boolean'],
            'bulk_received_date' => ['nullable', 'date'],
            'risk_level' => ['required', Rule::in(['Low', 'Medium', 'High'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
