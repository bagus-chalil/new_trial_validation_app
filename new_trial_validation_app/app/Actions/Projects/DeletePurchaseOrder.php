<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeletePurchaseOrder
{
    public function __construct(private readonly LogProjectActivity $log) {}

    public function __invoke(Project $project, PurchaseOrder $order, User $user): void
    {
        DB::transaction(function () use ($project, $order, $user) {
            $poNo = $order->po_no;
            $order->items()->delete();
            $order->delete();

            // The project's PO number falls back to its newest remaining PO.
            if ($project->po === $poNo) {
                $project->po = (string) (PurchaseOrder::query()->where('project_id', $project->id)->orderByDesc('id')->value('po_no') ?? '');
                $project->save();
            }

            ($this->log)($user, 'DELETE_PO', $project->id, $project->name, ['po_no' => $poNo]);
        });
    }
}
