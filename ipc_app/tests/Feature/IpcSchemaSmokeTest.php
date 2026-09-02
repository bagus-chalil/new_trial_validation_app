<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\FillingCheckSample;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcApproval;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcPrintLog;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterTestType;
use App\Models\PackingCheck;
use App\Models\StartupBottleWeight;
use App\Models\StartupCheck;
use App\Models\StartupInspection;
use App\Models\StartupInspectionItem;
use App\Models\StartupInspectionSample;
use App\Models\StartupInspectionTestResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcSchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_ipc_batch_schema_chain_persists_and_relates_correctly(): void
    {
        $user = User::factory()->create();

        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01']);
        $product = MasterProduct::create(['fg_code' => 'FG-001', 'product_name' => 'Test Product', 'bulk_code' => 'BULK-001']);
        $testType = MasterTestType::create(['name' => 'VACCUM', 'category' => MasterTestType::CATEGORY_LEAKAGE]);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);

        $startupCheck = StartupCheck::create([
            'ipc_batch_id' => $batch->id,
            'user_id' => $user->id,
            'product_standard_status' => StartupCheck::STATUS_AVAILABLE,
            'filling_range_min' => 10.5,
            'filling_range_max' => 12.5,
        ]);
        StartupBottleWeight::create(['startup_check_id' => $startupCheck->id, 'sample_no' => 1, 'weight_value' => 21.5]);
        StartupBottleWeight::create(['startup_check_id' => $startupCheck->id, 'sample_no' => 2, 'weight_value' => 21.8]);

        $startupInspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id]);
        StartupInspectionItem::create(['startup_inspection_id' => $startupInspection->id, 'parameter_key' => 'bulk_odor', 'status' => StartupInspectionItem::STATUS_OK]);
        StartupInspectionSample::create(['startup_inspection_id' => $startupInspection->id, 'sample_no' => 1, 'volume' => 100.5]);
        StartupInspectionTestResult::create(['startup_inspection_id' => $startupInspection->id, 'master_test_type_id' => $testType->id, 'is_performed' => true]);

        $fillingCheck = FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'decision' => FillingCheck::DECISION_PASSED]);
        FillingCheckSample::create(['filling_check_id' => $fillingCheck->id, 'sample_no' => 1, 'weight_value' => 30.5]);

        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'decision' => PackingCheck::DECISION_PASSED]);

        $finishedCheck = FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'disposition' => FinishedCheck::DISPOSITION_ACCEPTED]);
        FinishedCheckSample::create(['finished_check_id' => $finishedCheck->id, 'parameter_key' => 'functional_test', 'ac' => 0, 'cd' => 0, 'md' => 0, 'mnd' => 0]);

        IpcApproval::create(['ipc_batch_id' => $batch->id, 'stage' => IpcApproval::STAGE_STARTUP, 'decision' => 'Approved', 'approver_user_id' => $user->id, 'approved_at' => now()]);
        IpcPrintLog::create(['ipc_batch_id' => $batch->id, 'stage' => IpcApproval::STAGE_STARTUP, 'printed_by_user_id' => $user->id, 'printed_at' => now()]);
        IpcAttachment::create(['ipc_batch_id' => $batch->id, 'stage' => IpcApproval::STAGE_STARTUP, 'field_label' => 'im_number', 'file_path' => 'batches/1/im.jpg', 'uploaded_by' => $user->id]);

        $fresh = IpcBatch::with([
            'masterProduct', 'masterLine', 'creator',
            'startupCheck.bottleWeights',
            'startupInspection.items', 'startupInspection.samples', 'startupInspection.testResults',
            'fillingCheck.samples', 'packingCheck', 'finishedCheck.samples',
            'approvals', 'printLogs', 'attachments',
        ])->find($batch->id);

        $this->assertSame('Test Product', $fresh->masterProduct->product_name);
        $this->assertSame('Make Up 01', $fresh->masterLine->name);
        $this->assertSame($user->id, $fresh->creator->id);
        $this->assertCount(2, $fresh->startupCheck->bottleWeights);
        $this->assertCount(1, $fresh->startupInspection->items);
        $this->assertCount(1, $fresh->startupInspection->samples);
        $this->assertCount(1, $fresh->startupInspection->testResults);
        $this->assertCount(1, $fresh->fillingCheck->samples);
        $this->assertSame(PackingCheck::DECISION_PASSED, $fresh->packingCheck->decision);
        $this->assertCount(1, $fresh->finishedCheck->samples);
        $this->assertCount(1, $fresh->approvals);
        $this->assertCount(1, $fresh->printLogs);
        $this->assertCount(1, $fresh->attachments);

        // Soft-delete on the batch itself; child stage rows stay (they don't carry their own
        // deleted_at — recycle bin operates at the batch level, see ipc_app/CLAUDE.md).
        $batch->delete();
        $this->assertNull(IpcBatch::find($batch->id));
        $this->assertNotNull(IpcBatch::withTrashed()->find($batch->id));
    }
}
