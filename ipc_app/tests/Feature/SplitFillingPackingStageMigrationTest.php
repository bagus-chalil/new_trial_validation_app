<?php

namespace Tests\Feature;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SplitFillingPackingStageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_rows_are_carried_over_to_the_sides_each_flow_has(): void
    {
        $user = User::factory()->create();
        $product = MasterProduct::create(['fg_code' => 'FG-M', 'product_name' => 'Product M', 'is_active' => true]);
        $legacy = [];

        foreach ([IpcBatch::FLOW_FULL, IpcBatch::FLOW_FILLING, IpcBatch::FLOW_PACKING_FG] as $flow) {
            $batch = IpcBatch::create([
                'master_product_id' => $product->id,
                'no_batch' => "MIG-{$flow}",
                'created_by' => $user->id,
                'current_stage' => IpcBatch::STAGE_PRINT,
                'flow_type' => $flow,
            ]);
            $approvalId = DB::table('ipc_approvals')->insertGetId([
                'ipc_batch_id' => $batch->id,
                'stage' => 'filling_packing',
                'decision' => 'Approved',
                'approver_user_id' => $user->id,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ipc_approval_revisions')->insert([
                'ipc_approval_id' => $approvalId,
                'revision_no' => 1,
                'decision' => 'Approved',
                'approver_user_id' => $user->id,
                'decided_at' => now(),
            ]);
            DB::table('ipc_print_logs')->insert([
                'ipc_batch_id' => $batch->id,
                'stage' => 'filling_packing',
                'printed_by_user_id' => $user->id,
                'printed_at' => now(),
            ]);
            $legacy[$flow] = $batch;
        }

        (require database_path('migrations/2026_10_20_000000_split_filling_packing_stage.php'))->up();

        $stagesOf = fn (string $table, IpcBatch $batch) => DB::table($table)->where('ipc_batch_id', $batch->id)->orderBy('stage')->pluck('stage')->all();

        $this->assertSame(['filling', 'packing'], $stagesOf('ipc_approvals', $legacy[IpcBatch::FLOW_FULL]));
        $this->assertSame(['filling', 'packing'], $stagesOf('ipc_print_logs', $legacy[IpcBatch::FLOW_FULL]));
        $this->assertSame(['filling'], $stagesOf('ipc_approvals', $legacy[IpcBatch::FLOW_FILLING]));
        $this->assertSame(['packing'], $stagesOf('ipc_approvals', $legacy[IpcBatch::FLOW_PACKING_FG]));
        $this->assertSame(['packing'], $stagesOf('ipc_print_logs', $legacy[IpcBatch::FLOW_PACKING_FG]));
        $this->assertSame(0, DB::table('ipc_approvals')->where('stage', 'filling_packing')->count());

        // Full flow's copy keeps its own revision history, and nothing already Approved is lost.
        $packing = IpcApproval::where('ipc_batch_id', $legacy[IpcBatch::FLOW_FULL]->id)->where('stage', 'packing')->first();
        $this->assertSame('Approved', $packing->decision);
        $this->assertSame(1, $packing->revisions()->count());
    }
}
