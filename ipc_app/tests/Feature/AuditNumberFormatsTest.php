<?php

namespace Tests\Feature;

use App\Models\FinishedCheck;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditNumberFormatsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-A', 'product_name' => 'Product A', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 09', 'name' => 'Make Up 09', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-AUDIT',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_PACKING,
        ]);
    }

    public function test_audit_passes_when_every_value_is_well_formed_and_never_writes(): void
    {
        $batch = $this->makeBatch();
        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'standard_weight_mb' => '1920.5-2000']);
        FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'masterbox' => '4,17,55', 'no_pallet_qty' => '1/2016,2/2016']);

        $this->artisan('ipc:audit-number-formats')->assertExitCode(0);
    }

    public function test_audit_reports_a_decimal_comma_without_changing_it(): void
    {
        $batch = $this->makeBatch();
        $check = PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'standard_weight_mb' => '1920,5']);

        $this->artisan('ipc:audit-number-formats')->assertExitCode(1);

        $this->assertSame('1920,5', $check->fresh()->standard_weight_mb);
    }

    public function test_migration_rewrites_decimal_comma_to_dot_and_leaves_list_columns_alone(): void
    {
        $batch = $this->makeBatch();
        $packing = PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'standard_weight_mb' => '1920,5-2000,25']);
        $finished = FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'masterbox' => '4,17,55', 'no_pallet_qty' => '1/2016,2/2016']);

        $migration = require database_path('migrations/2026_11_18_120000_normalize_decimal_comma_in_standard_weight_mb.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertSame('1920.5-2000.25', $packing->fresh()->standard_weight_mb);
        $this->assertSame('4,17,55', $finished->fresh()->masterbox);
        $this->assertSame('1/2016,2/2016', DB::table('finished_checks')->where('id', $finished->id)->value('no_pallet_qty'));
    }
}
