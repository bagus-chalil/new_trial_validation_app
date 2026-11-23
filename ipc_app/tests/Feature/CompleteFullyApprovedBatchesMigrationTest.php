<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompleteFullyApprovedBatchesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = '2026-10-15 09:00:00';

    private function migration(): object
    {
        return require database_path('migrations/2026_11_18_100000_complete_fully_approved_batches.php');
    }

    private function batch(string $no, string $flow, string $stage = IpcBatch::STAGE_APPROVAL): IpcBatch
    {
        $batch = IpcBatch::create([
            'master_product_id' => MasterProduct::firstOrCreate(['fg_code' => 'FG-C'], ['product_name' => 'Product C', 'is_active' => true])->id,
            'no_batch' => $no,
            'created_by' => User::factory()->create()->id,
            'current_stage' => $stage,
            'flow_type' => $flow,
        ]);

        // Pin updated_at in the past so a migration that touched it would be caught.
        DB::table('ipc_batches')->where('id', $batch->id)->update(['updated_at' => self::OLD]);

        return $batch;
    }

    /** @param  list<string>  $stages */
    private function approve(IpcBatch $batch, array $stages, string $decision = 'Approved'): void
    {
        foreach ($stages as $stage) {
            DB::table('ipc_approvals')->insert([
                'ipc_batch_id' => $batch->id,
                'stage' => $stage,
                'decision' => $decision,
                'approver_user_id' => $batch->created_by,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function stageOf(IpcBatch $batch): string
    {
        return (string) DB::table('ipc_batches')->where('id', $batch->id)->value('current_stage');
    }

    public function test_fully_approved_batches_of_every_flow_move_to_completed_without_touching_anything_else(): void
    {
        $filling = $this->batch('FILLING', IpcBatch::FLOW_FILLING);
        $this->approve($filling, ['filling']);

        $packing = $this->batch('PACKING', IpcBatch::FLOW_PACKING_FG);
        $this->approve($packing, ['startup', 'packing', 'finished']);

        $full = $this->batch('FULL', IpcBatch::FLOW_FULL);
        $this->approve($full, ['startup', 'filling', 'packing', 'finished']);

        $approvalsBefore = DB::table('ipc_approvals')->count();

        $this->migration()->up();

        foreach ([$filling, $packing, $full] as $batch) {
            $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($batch));
            $this->assertSame(self::OLD, DB::table('ipc_batches')->where('id', $batch->id)->value('updated_at'));
        }
        $this->assertSame($approvalsBefore, DB::table('ipc_approvals')->count());
        $this->assertSame(3, DB::table('ipc_approved_batches_completed')->count());
    }

    public function test_batches_that_are_not_fully_approved_or_not_at_approval_are_left_alone(): void
    {
        $missingPart = $this->batch('MISSING', IpcBatch::FLOW_FULL);
        $this->approve($missingPart, ['startup', 'filling', 'packing']); // finished not decided yet

        $rejected = $this->batch('REJECTED', IpcBatch::FLOW_FULL);
        $this->approve($rejected, ['startup', 'filling', 'finished']);
        $this->approve($rejected, ['packing'], 'Rejected');

        $noApprovals = $this->batch('NONE', IpcBatch::FLOW_FILLING);

        $deleted = $this->batch('DELETED', IpcBatch::FLOW_FILLING);
        $this->approve($deleted, ['filling']);
        DB::table('ipc_batches')->where('id', $deleted->id)->update(['deleted_at' => now()]);

        $inFilling = $this->batch('INFILLING', IpcBatch::FLOW_FILLING, IpcBatch::STAGE_FILLING);
        $this->approve($inFilling, ['filling']);

        $this->migration()->up();

        $this->assertSame(IpcBatch::STAGE_APPROVAL, $this->stageOf($missingPart));
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $this->stageOf($rejected));
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $this->stageOf($noApprovals));
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $this->stageOf($deleted));
        $this->assertSame(IpcBatch::STAGE_FILLING, $this->stageOf($inFilling));
        $this->assertSame(0, DB::table('ipc_approved_batches_completed')->count());
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $batch = $this->batch('TWICE', IpcBatch::FLOW_FILLING);
        $this->approve($batch, ['filling']);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($batch));
        $this->assertSame(1, DB::table('ipc_approved_batches_completed')->count());
    }

    public function test_rollback_puts_back_only_the_batches_the_migration_moved(): void
    {
        $moved = $this->batch('MOVED', IpcBatch::FLOW_FILLING);
        $this->approve($moved, ['filling']);
        $alreadyDone = $this->batch('ALREADY', IpcBatch::FLOW_FILLING, IpcBatch::STAGE_COMPLETED);

        $migration = $this->migration();
        $migration->up();
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($moved));

        // down() = restoreBatches() + dropping the tracking table; the DDL half is left out here
        // because MySQL commits implicitly on DROP TABLE, which would leak rows between tests.
        $migration->restoreBatches();

        $this->assertSame(IpcBatch::STAGE_APPROVAL, $this->stageOf($moved));
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($alreadyDone));
    }
}
