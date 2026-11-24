<?php

namespace App\Http\Requests\Projects;

use App\Support\PurchaseOrderSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavePurchaseOrderTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-projects');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:190'],
            'doc_code' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'string', 'max:400000'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact' => ['nullable', 'string', 'max:1000'],
            'title' => ['required', 'string', 'max:120'],
            'pattern' => ['required', 'string', 'max:120', 'regex:/\{NNN\}/'],
            'date_format' => ['required', Rule::in(PurchaseOrderSettings::DATE_FORMATS)],
            'lang' => ['required', Rule::in(PurchaseOrderSettings::LANGUAGES)],
            'tax' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'lead_time' => ['nullable', 'string', 'max:255'],
            'delivery' => ['nullable', 'string', 'max:255'],
            'ship_name' => ['nullable', 'string', 'max:190'],
            'ship_address' => ['nullable', 'string', 'max:1000'],
            'ship_tel' => ['nullable', 'string', 'max:80'],
            'ship_attn' => ['nullable', 'string', 'max:150'],
            'billing' => ['nullable', 'string', 'max:3000'],
            'notice' => ['nullable', 'string', 'max:5000'],
            'signers' => ['nullable', 'array', 'max:3'],
            'signers.*.label' => ['nullable', 'string', 'max:60'],
            'signers.*.name' => ['nullable', 'string', 'max:100'],
            'signers.*.title' => ['nullable', 'string', 'max:100'],
            'signers.*.image' => ['nullable', 'string', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return ['pattern.regex' => __('purchase_orders.template.pattern_needs_nnn')];
    }

    /**
     * @return array<string, mixed>
     */
    public function templateData(): array
    {
        $data = $this->validated();
        $data['logo'] = PurchaseOrderSettings::safeImage($data['logo'] ?? '', 300 * 1024);
        $data['tax'] = (float) ($data['tax'] ?? 0);

        return $data;
    }
}
