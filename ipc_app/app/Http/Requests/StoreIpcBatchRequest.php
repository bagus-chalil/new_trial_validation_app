<?php

namespace App\Http\Requests;

use App\Models\IpcBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIpcBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('no_batch')) {
            $this->merge(['no_batch' => strtoupper((string) $this->input('no_batch'))]);
        }
    }

    public function rules(): array
    {
        return [
            'master_product_id' => ['required', Rule::exists('master_products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'master_product_bulk_code_ids' => ['required', 'array', 'min:1'],
            'master_product_bulk_code_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('master_product_bulk_codes', 'id')
                    ->where('master_product_id', $this->input('master_product_id'))
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            // 2 digits + 1 letter + 3 digits + "/" + 3 letters (26Y034/JLB), same as the bulk identity.
            // Only enforced on creation: older batches (spaces, missing letters) are left as they are.
            'no_batch' => ['required', 'string', 'max:100', 'regex:#^\d{2}[A-Z]\d{3}/[A-Z]{3}$#'],
            // Months, from the IM / WI. Required so Master Produk gets filled over time; EXP date is
            // calculated from it on Startup Check.
            'shelf_life_months' => ['required', 'integer', 'min:1', 'max:120'],
            // Label Bulk photo scanned on this form (BatchLabelScanController); optional, operator may type everything.
            'label_scan_id' => ['nullable', 'uuid'],
            'flow_type' => ['required', Rule::in(IpcBatch::FLOW_TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'no_batch.regex' => 'Format No Batch FG harus 2 angka + 1 huruf + 3 angka + "/" + 3 huruf, contoh 26Y034/JLB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'shelf_life_months' => 'shelf life',
            'master_product_bulk_code_ids' => 'bulk code',
            'master_product_bulk_code_ids.*' => 'bulk code',
        ];
    }
}
