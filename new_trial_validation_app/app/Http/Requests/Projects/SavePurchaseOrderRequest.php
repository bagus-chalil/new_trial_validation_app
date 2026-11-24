<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchaseOrder');
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $order instanceof PurchaseOrder
            ? $user->can('update', $order)
            : $user->can('create', PurchaseOrder::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $order = $this->route('purchaseOrder');
        $projectId = $order instanceof PurchaseOrder ? $order->project_id : (int) $this->route('project');

        return [
            'po_no' => ['required', 'string', 'max:120', Rule::unique('pm_purchase_orders', 'po_no')->ignore($order instanceof PurchaseOrder ? $order->id : null)],
            'po_date' => ['required', 'date'],
            'quotation_id' => ['nullable', 'integer', Rule::exists('pm_quotations', 'id')->where('project_id', $projectId)],
            'vendor_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:8'],
            'vendor_address' => ['nullable', 'string', 'max:1000'],
            'attn' => ['nullable', 'string', 'max:150'],
            'tel' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'string', 'max:190'],
            'quotation_no' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:255'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'delivery' => ['nullable', 'string', 'max:255'],
            'lead_time' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'ship_name' => ['nullable', 'string', 'max:190'],
            'ship_address' => ['nullable', 'string', 'max:1000'],
            'ship_tel' => ['nullable', 'string', 'max:80'],
            'ship_attn' => ['nullable', 'string', 'max:150'],
            'use_signature' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:2000'],
            'items.*.qty' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'items.*.due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'po_no' => __('purchase_orders.fields.po_no'),
            'po_date' => __('purchase_orders.fields.po_date'),
            'vendor_name' => __('purchase_orders.fields.vendor'),
            'items' => __('purchase_orders.items.title'),
            'items.*.description' => __('purchase_orders.items.description'),
            'items.*.qty' => __('purchase_orders.items.qty'),
            'items.*.unit_price' => __('purchase_orders.items.unit_price'),
        ];
    }

    public function project(): Project
    {
        $order = $this->route('purchaseOrder');
        $id = $order instanceof PurchaseOrder ? $order->project_id : (int) $this->route('project');

        return Project::whereNull('deleted_at')->findOrFail($id);
    }
}
