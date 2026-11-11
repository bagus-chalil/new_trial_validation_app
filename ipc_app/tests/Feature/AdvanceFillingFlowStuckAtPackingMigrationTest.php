<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceFillingFlowStuckAtPackingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function batch(string $flow, string $stage, string $no): IpcBatch
    {
        $user = User::first() ?? User::factory()->create();
        $product = MasterProduct::first() ?? MasterProduct::create(['fg_code' => 'FG-S', 'product_name' => 'Product S', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => $no,
            'created_by' => $user->id,
            'current_stage' => $stage,
            'flow_type' => $flow,
        ]);
    }

    public function test_only_stuck_filling_flow_batches_with_a_finalized_filling_check_are_moved(): void
    {
        $stuck = $this->batch(IpcBatch::FLOW_FILLING, IpcBatch::STAGE_PACKING, 'STUCK');
        FillingCheck::create(['user_id' => User::first()->id, 'ipc_batch_id' => $stuck->id, 'completed_at' => now()]);

        $notFinalized = $this->batch(IpcBatch::FLOW_FILLING, IpcBatch::STAGE_PACKING, 'NOT-FINAL');
        FillingCheck::create(['user_id' => User::first()->id, 'ipc_batch_id' => $notFinalized->id]);

        $full = $this->batch(IpcBatch::FLOW_FULL, IpcBatch::STAGE_PACKING, 'FULL');
        FillingCheck::create(['user_id' => User::first()->id, 'ipc_batch_id' => $full->id, 'completed_at' => now()]);

        $withPacking = $this->batch(IpcBatch::FLOW_FILLING, IpcBatch::STAGE_PACKING, 'HAS-PACKING');
        FillingCheck::create(['user_id' => User::first()->id, 'ipc_batch_id' => $withPacking->id, 'completed_at' => now()]);
        PackingCheck::create(['user_id' => User::first()->id, 'ipc_batch_id' => $withPacking->id]);

        $atFilling = $this->batch(IpcBatch::FLOW_FILLING, IpcBatch::STAGE_FILLING, 'AT-FILLING');
        FillingCheck::create(['user_id' => User::first()->id, 'ipc_batch_id' => $atFilling->id, 'completed_at' => now()]);

        (require database_path('migrations/2026_11_11_090000_advance_filling_flow_batches_stuck_at_packing.php'))->up();

        $this->assertSame(IpcBatch::STAGE_APPROVAL, $stuck->fresh()->current_stage);
        $this->assertSame(IpcBatch::STAGE_PACKING, $notFinalized->fresh()->current_stage);
        $this->assertSame(IpcBatch::STAGE_PACKING, $full->fresh()->current_stage);
        $this->assertSame(IpcBatch::STAGE_PACKING, $withPacking->fresh()->current_stage);
        $this->assertSame(IpcBatch::STAGE_FILLING, $atFilling->fresh()->current_stage);
    }
}
