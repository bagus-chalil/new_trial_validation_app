<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackingCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchWithCompletedFillingCheck(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_PACKING,
        ]);

        FillingCheck::create([
            'ipc_batch_id' => $batch->id,
            'user_id' => $batch->created_by,
            'decision' => FillingCheck::DECISION_PASSED,
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    private function validPayload(): array
    {
        $checklist = [];
        foreach (PackingCheck::checklistGroups() as $group) {
            $checklist = [...$checklist, ...array_fill_keys(array_keys($group['fields']), $group['options'][0])];
        }

        return [
            ...$checklist,
            'standard_weight_mb' => 10.5,
            'sum_weight_mb' => 105.0,
            'line_leader_name' => 'Budi',
            'coding_machine' => 'CM-01',
            'remarks' => 'OK',
            'decision' => PackingCheck::DECISION_PASSED,
        ];
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->get("/batches/{$batch->id}/packing-check")->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_form(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->get("/batches/{$batch->id}/packing-check")->assertOk();
    }

    public function test_form_is_forbidden_when_filling_check_is_not_completed(): void
    {
        $this->actingAs(User::factory()->create());
        $product = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'bulk_code' => 'BULK-2', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 02', 'name' => 'Make Up 02', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-002',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_FILLING,
        ]);

        $this->get("/batches/{$batch->id}/packing-check")->assertForbidden();
    }

    public function test_soft_deleted_batch_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $batch->delete();

        $this->get("/batches/{$batch->id}/packing-check")->assertNotFound();
    }

    public function test_valid_submission_persists_check_and_advances_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->current_stage);

        $packingCheck = $batch->packingCheck()->first();
        $this->assertNotNull($packingCheck->completed_at);
        $this->assertSame(PackingCheck::STATUS_CONFORM, $packingCheck->primary_bulk_status);
        $this->assertSame(PackingCheck::DECISION_PASSED, $packingCheck->decision);
    }

    public function test_missing_checklist_field_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $payload = $this->validPayload();
        unset($payload['primary_bulk_status']);

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertSessionHasErrors('primary_bulk_status');

        $this->assertNull($batch->fresh()->packingCheck);
    }

    public function test_missing_remarks_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $payload = $this->validPayload();
        unset($payload['remarks']);

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertSessionHasErrors('remarks');
    }

    public function test_secondary_coding_na_accepts_the_tri_state_value(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $payload = $this->validPayload();
        $payload['secondary_coding_na_status'] = PackingCheck::STATUS_NA;

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertRedirect("/batches/{$batch->id}");

        $this->assertSame(PackingCheck::STATUS_NA, $batch->fresh()->packingCheck->secondary_coding_na_status);
    }

    public function test_completed_packing_check_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())->assertRedirect("/batches/{$batch->id}");

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())->assertForbidden();
    }
}
