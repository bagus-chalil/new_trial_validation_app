<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NormalizeSpaceNoBatchMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = '2026-10-15 09:00:00';

    private function migration(): object
    {
        // RefreshDatabase already ran it on the empty DB; start from a clean slate to run it on our rows.
        Schema::dropIfExists('ipc_no_batch_normalized');

        return require database_path('migrations/2026_11_19_100000_normalize_space_separated_no_batch.php');
    }

    private function batch(string $no): IpcBatch
    {
        $batch = IpcBatch::create([
            'master_product_id' => MasterProduct::firstOrCreate(['fg_code' => 'FG-N'], ['product_name' => 'Product N', 'is_active' => true])->id,
            'no_batch' => $no,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
            'flow_type' => IpcBatch::FLOW_FULL,
        ]);
        DB::table('ipc_batches')->where('id', $batch->id)->update(['updated_at' => self::OLD]);

        return $batch;
    }

    private function noBatch(IpcBatch $batch): string
    {
        return (string) DB::table('ipc_batches')->where('id', $batch->id)->value('no_batch');
    }

    public function test_only_space_variants_are_rewritten_and_everything_else_is_left_alone(): void
    {
        $single = $this->batch('26X004 DKB');
        $double = $this->batch('26S009  JEB');
        $already = $this->batch('26Y034/JLB');
        $untouched = collect(['AJB', '26X1234', '26X001', '26X001N', '26XP006/FKB', 'KKB 26X035', '26X10/KKB', '26X006 FKBX'])
            ->map(fn (string $no) => [$no, $this->batch($no)]);

        $this->migration()->up();

        $this->assertSame('26X004/DKB', $this->noBatch($single));
        $this->assertSame('26S009/JEB', $this->noBatch($double));
        $this->assertSame('26Y034/JLB', $this->noBatch($already));
        foreach ($untouched as [$no, $batch]) {
            $this->assertSame($no, $this->noBatch($batch));
        }
        $this->assertSame(2, DB::table('ipc_no_batch_normalized')->count());
        // updated_at must not move.
        $this->assertSame(self::OLD, (string) DB::table('ipc_batches')->where('id', $single->id)->value('updated_at'));
    }

    public function test_down_restores_the_old_value_unless_it_was_edited_since(): void
    {
        $kept = $this->batch('26X004 DKB');
        $edited = $this->batch('26W003 CJB');

        $migration = $this->migration();
        $migration->up();
        DB::table('ipc_batches')->where('id', $edited->id)->update(['no_batch' => '26W003/ZZZ']);
        $migration->down();

        $this->assertSame('26X004 DKB', $this->noBatch($kept));
        $this->assertSame('26W003/ZZZ', $this->noBatch($edited));
    }
}
