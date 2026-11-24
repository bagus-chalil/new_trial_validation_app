<?php

namespace App\Http\Requests\Projects;

use App\Support\ProjectOptions;
use Illuminate\Validation\Rule;

class SaveProjectQuotationRequest extends ProjectChildRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vendor_name' => ['required', 'string', 'max:255'],
            'quotation_no' => ['nullable', 'string', 'max:120'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'currency' => ['required', 'string', 'max:8'],
            'quotation_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'status' => ['required', Rule::in(ProjectOptions::QUOTATION_STATUSES)],
            'note' => ['nullable', 'string', 'max:2000'],
            'link' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vendor_name' => __('projects.quotation.vendor'),
            'quotation_no' => __('projects.quotation.number'),
            'amount' => __('projects.quotation.amount'),
            'quotation_date' => __('projects.quotation.date'),
            'valid_until' => __('projects.quotation.valid_until'),
            'status' => __('projects.fields.status'),
            'link' => __('projects.quotation.link'),
        ];
    }
}
