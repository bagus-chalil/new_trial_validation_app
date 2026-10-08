<?php

namespace Tests\Feature;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceApprovedBatchesTest extends TestCase
{
    use RefreshDatabase;

    private function batchAtApproval(string $noBatch, array $approvedStages): IpcBatch
    {
        $product = MasterProduct::firstOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::firstOrCreate(['code' => 'MU 01'], ['category' => 'Packing', 'area' => 'Make Up', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->approver()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => $noBatch,
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
        ]);

        foreach ($approvedStages as $stage) {
            IpcApproval::create([
                'ipc_batch_id' => $batch->id,
                'stage' => $stage,
                'decision' => IpcApproval::DECISION_APPROVED,
                'approver_user_id' => $user->id,
                'approved_at' => now(),
            ]);
        }

        return $batch;
    }

    public function test_fully_approved_batch_stuck_at_approval_is_advanced_to_print(): void
    {
        $stuck = $this->batchAtApproval('STUCK', IpcApproval::APPROVAL_REQUIRED_STAGES);
        $partial = $this->batchAtApproval('PARTIAL', [IpcApproval::STAGE_FILLING_PACKING]);

        $this->artisan('ipc:advance-approved-batches')->assertSuccessful();

        $this->assertSame(IpcBatch::STAGE_PRINT, $stuck->fresh()->current_stage);
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $partial->fresh()->current_stage);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $stuck = $this->batchAtApproval('STUCK', IpcApproval::APPROVAL_REQUIRED_STAGES);

        $this->artisan('ipc:advance-approved-batches --dry-run')->assertSuccessful();

        $this->assertSame(IpcBatch::STAGE_APPROVAL, $stuck->fresh()->current_stage);
    }
}
