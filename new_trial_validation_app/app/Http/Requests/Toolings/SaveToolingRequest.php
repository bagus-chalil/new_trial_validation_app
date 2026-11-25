<?php

namespace App\Http\Requests\Toolings;

use App\Models\Tooling;
use App\Support\ToolingOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The PRNI request part (section A) of a Check Tooling, create and edit. */
class SaveToolingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tooling = $this->route('tooling');

        return $tooling
            ? (bool) $this->user()?->can('update', Tooling::whereNull('deleted_at')->where('id', (int) $tooling)->firstOrFail())
            : (bool) $this->user()?->can('create', Tooling::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_critical' => $this->boolean('is_critical')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $text = ['nullable', 'string', 'max:5000'];

        return [
            'drawings' => ['nullable', 'array', 'max:10'],
            'drawings.*' => ['file'],
            'npd_no' => ['required', 'string', 'max:120'],
            'request_date' => ['required', 'date'],
            'requested_by_name' => ['required', 'string', 'max:100'],
            'client_code' => ['required', 'string', 'max:100'],
            'product_name' => ['required', 'string', 'max:255'],
            'marketing' => ['nullable', 'string', 'max:120'],
            'packdev' => ['nullable', 'string', 'max:120'],
            'product_type' => ['required', Rule::in(ToolingOptions::PRODUCT_TYPES)],
            'packaging_type' => ['required', 'string', 'max:255'],
            'quantity_po' => ['nullable', 'string', 'max:120'],
            'launch_date' => ['nullable', 'string', 'max:120'],
            'dimension_packaging' => $text,
            'material_packaging' => $text,
            'bom_primary' => ['required', 'string', 'max:5000'],
            'pm_ref_detail' => $text,
            'is_critical' => ['boolean'],
            'links' => ['nullable', 'array', 'max:20'],
            'links.*.title' => ['nullable', 'string', 'max:200'],
            'links.*.url' => ['nullable', 'url:http,https', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'npd_no' => __('toolings.fields.npd_no'),
            'request_date' => __('toolings.fields.request_date'),
            'requested_by_name' => __('toolings.fields.requested_by_name'),
            'client_code' => __('toolings.fields.client_code'),
            'bom_primary' => __('toolings.fields.bom_primary'),
            'product_name' => __('toolings.fields.product_name'),
            'product_type' => __('toolings.fields.product_type'),
            'packaging_type' => __('toolings.fields.packaging_type'),
            'links.*.url' => __('toolings.fields.link_url'),
        ];
    }
}
