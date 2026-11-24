<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\VendorContact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a Purchase Order with its items. Side effects (legacy
 * behaviour): a vendor not yet in the contact book is added to it; the first
 * save of a PO completes the project's open "PO" stage; the project's `po`
 * number follows the latest PO.
 */
class SavePurchaseOrder
{
    public function __construct(
        private readonly LogProjectActivity $log,
        private readonly RecalculateProjectProgress $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated request data
     */
    public function __invoke(Project $project, ?PurchaseOrder $order, array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($project, $order, $data, $user) {
            $attributes = [
                'quotation_id' => $data['quotation_id'] ?? null,
                'po_no' => trim($data['po_no']),
                'po_date' => $data['po_date'],
                'vendor_name' => trim($data['vendor_name']),
                'currency' => strtoupper(trim($data['currency'])),
                'vendor_address' => $data['vendor_address'] ?? null,
                'attn' => trim((string) ($data['attn'] ?? '')),
                'tel' => trim((string) ($data['tel'] ?? '')),
                'email' => trim((string) ($data['email'] ?? '')),
                'quotation_no' => trim((string) ($data['quotation_no'] ?? '')),
                'subject' => trim((string) ($data['subject'] ?? '')),
                'tax_percent' => $data['tax_percent'] ?? 0,
                'terms' => $data['terms'] ?? null,
                'delivery' => trim((string) ($data['delivery'] ?? '')),
                'lead_time' => trim((string) ($data['lead_time'] ?? '')),
                'note' => $data['note'] ?? null,
                'ship_name' => trim((string) ($data['ship_name'] ?? '')),
                'ship_address' => $data['ship_address'] ?? null,
                'ship_tel' => trim((string) ($data['ship_tel'] ?? '')),
                'ship_attn' => trim((string) ($data['ship_attn'] ?? '')),
                'use_signature' => (bool) ($data['use_signature'] ?? false),
            ];

            $creating = $order === null;
            if ($creating) {
                $order = new PurchaseOrder;
                $order->fill($attributes + [
                    'project_id' => $project->id,
                    'created_by_user_id' => $user->id,
                    'created_by_name' => $user->name,
                ])->save();
            } else {
                $old = $order->only(['po_no', 'vendor_name']);
                $order->fill($attributes)->save();
            }

            $order->items()->delete();
            foreach (array_values($data['items']) as $i => $item) {
                $order->items()->create([
                    'sort_order' => $i,
                    'description' => trim($item['description']),
                    'qty' => $item['qty'],
                    'unit' => trim((string) ($item['unit'] ?? '')) ?: 'Unit',
                    'unit_price' => $item['unit_price'],
                    'due_date' => $item['due_date'] ?? null,
                ]);
            }

            $this->rememberVendor($order);

            $project->po = $order->po_no;
            if (trim((string) $project->vendor) === '') {
                $project->vendor = $order->vendor_name;
            }
            $project->save();

            if ($creating) {
                $this->completePoStage($project, $order);
            }

            ($this->log)(
                $user,
                $creating ? 'CREATE_PO' : 'UPDATE_PO',
                $project->id,
                $project->name,
                $creating ? null : $old,
                ['po_no' => $order->po_no, 'vendor' => $order->vendor_name],
            );

            return $order->refresh();
        });
    }

    private function rememberVendor(PurchaseOrder $order): void
    {
        $exists = VendorContact::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($order->vendor_name)])->exists();
        if ($exists) {
            return;
        }

        VendorContact::create([
            'name' => $order->vendor_name,
            'contact' => $order->attn,
            'phone' => $order->tel,
            'email' => $order->email,
            'address' => $order->vendor_address,
        ]);
    }

    /** The first open stage named "PO" / "Purchase Order" becomes Completed on the PO date. */
    private function completePoStage(Project $project, PurchaseOrder $order): void
    {
        $stage = $project->stages()
            ->where('status', '!=', 'Completed')
            ->orderBy('sort_order')
            ->get()
            ->first(fn ($s) => preg_match('/\bPO\b|purchase order/i', (string) $s->name) === 1);

        if (! $stage) {
            return;
        }

        $date = Carbon::parse($order->po_date)->startOfDay();
        $start = $stage->actual_start;
        if (! $start) {
            $planStart = $stage->plan_start;
            $start = ($planStart && $planStart->lt($date)) ? $planStart : $date;
        }

        $stage->actual_start = $start;
        $stage->actual_end = $date;
        $stage->status = 'Completed';
        $stage->progress = 100;
        $stage->save();

        ($this->recalculate)($project);
    }
}
