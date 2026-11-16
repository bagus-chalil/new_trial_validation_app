<?php

namespace Tests\Feature;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RetirePrintStageMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = '2026-10-15 09:00:00';

    private function migration(): object
    {
        return require database_path('migrations/2026_11_16_120000_retire_print_stage.php');
    }

    private function batch(string $no, string $stage, string $flow = IpcBatch::FLOW_FULL): IpcBatch
    {
        $batch = IpcBatch::create([
            'master_product_id' => MasterProduct::firstOrCreate(['fg_code' => 'FG-R'], ['product_name' => 'Product R', 'is_active' => true])->id,
            'no_batch' => $no,
            'created_by' => User::factory()->create()->id,
            'current_stage' => $stage,
            'flow_type' => $flow,
        ]);

        // Pin updated_at in the past so a migration that touched it would be caught.
        DB::table('ipc_batches')->where('id', $batch->id)->update(['updated_at' => self::OLD]);

        return $batch;
    }

    private function approve(IpcBatch $batch, string $stage, string $decision = 'Approved'): void
    {
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

    private function stageOf(IpcBatch $batch): string
    {
        return (string) DB::table('ipc_batches')->where('id', $batch->id)->value('current_stage');
    }

    public function test_batches_at_print_move_to_completed_without_touching_anything_else(): void
    {
        $full = $this->batch('FULL', IpcBatch::STAGE_PRINT);
        // Approved under the old rules: no Startup approval row exists and none may be invented.
        $this->approve($full, 'filling');
        $this->approve($full, 'packing');
        $this->approve($full, 'finished');
        DB::table('ipc_print_logs')->insert(['ipc_batch_id' => $full->id, 'stage' => 'startup', 'printed_by_user_id' => $full->created_by, 'printed_at' => now()]);

        $filling = $this->batch('FILLING', IpcBatch::STAGE_PRINT, IpcBatch::FLOW_FILLING);
        $this->approve($filling, 'filling');

        $approvalsBefore = DB::table('ipc_approvals')->count();
        $logsBefore = DB::table('ipc_print_logs')->count();

        $this->migration()->up();

        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($full));
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($filling));
        $this->assertSame(self::OLD, DB::table('ipc_batches')->where('id', $full->id)->value('updated_at'));
        $this->assertSame($approvalsBefore, DB::table('ipc_approvals')->count());
        $this->assertSame($logsBefore, DB::table('ipc_print_logs')->count());
        $this->assertSame(0, DB::table('ipc_approvals')->where('ipc_batch_id', $full->id)->where('stage', IpcApproval::STAGE_STARTUP)->count());
        $this->assertSame(2, DB::table('ipc_print_stage_migrated')->count());
    }

    public function test_other_stages_deleted_and_not_fully_approved_batches_are_left_alone(): void
    {
        $approval = $this->batch('APPROVAL', IpcBatch::STAGE_APPROVAL);
        $completed = $this->batch('DONE', IpcBatch::STAGE_COMPLETED);
        $filling = $this->batch('FILLING', IpcBatch::STAGE_FILLING);

        $deleted = $this->batch('DELETED', IpcBatch::STAGE_PRINT);
        DB::table('ipc_batches')->where('id', $deleted->id)->update(['deleted_at' => now()]);

        $rejected = $this->batch('REJECTED', IpcBatch::STAGE_PRINT);
        $this->approve($rejected, 'filling');
        $this->approve($rejected, 'packing', 'Rejected');

        $this->migration()->up();

        $this->assertSame(IpcBatch::STAGE_APPROVAL, $this->stageOf($approval));
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($completed));
        $this->assertSame(IpcBatch::STAGE_FILLING, $this->stageOf($filling));
        $this->assertSame(IpcBatch::STAGE_PRINT, $this->stageOf($deleted));
        $this->assertSame(IpcBatch::STAGE_PRINT, $this->stageOf($rejected));
        $this->assertSame(0, DB::table('ipc_print_stage_migrated')->count());
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $batch = $this->batch('TWICE', IpcBatch::STAGE_PRINT);
        $this->approve($batch, 'filling');

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($batch));
        $this->assertSame(1, DB::table('ipc_print_stage_migrated')->count());
    }

    public function test_rollback_puts_back_only_the_batches_the_migration_moved(): void
    {
        $moved = $this->batch('MOVED', IpcBatch::STAGE_PRINT);
        $this->approve($moved, 'filling');
        $alreadyDone = $this->batch('ALREADY', IpcBatch::STAGE_COMPLETED);

        $migration = $this->migration();
        $migration->up();
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($moved));

        // down() = restoreBatches() + dropping the tracking table; the DDL half is left out here
        // because MySQL commits implicitly on DROP TABLE, which would leak rows between tests.
        $migration->restoreBatches();

        $this->assertSame(IpcBatch::STAGE_PRINT, $this->stageOf($moved));
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $this->stageOf($alreadyDone));
    }

    public function test_the_print_stage_is_no_longer_part_of_any_flow(): void
    {
        foreach ([IpcBatch::FLOW_FULL, IpcBatch::FLOW_FILLING, IpcBatch::FLOW_PACKING_FG] as $flow) {
            $batch = new IpcBatch(['flow_type' => $flow]);

            $this->assertNotContains(IpcBatch::STAGE_PRINT, $batch->activeStages());
            $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->nextStageAfter(IpcBatch::STAGE_APPROVAL));
        }
        $this->assertNotContains(IpcBatch::STAGE_PRINT, IpcBatch::STAGES);
    }
}
