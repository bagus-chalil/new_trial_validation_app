<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterTestType;
use App\Models\StartupInspectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartupInspectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);
    }

    private function makeTestType(string $name = 'VACCUM', string $category = MasterTestType::CATEGORY_LEAKAGE): MasterTestType
    {
        return MasterTestType::create(['name' => $name, 'category' => $category, 'is_active' => true]);
    }

    private function validItemsPayload(): array
    {
        $items = [];
        foreach (StartupInspectionItem::PARAMETER_KEYS as $key) {
            $items[$key] = ['status' => StartupInspectionItem::STATUS_OK, 'remark' => null];
        }

        return $items;
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $batch = $this->makeBatch();

        $this->get("/batches/{$batch->id}/startup-inspection")->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_form_without_startup_check_being_done(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->get("/batches/{$batch->id}/startup-inspection")->assertOk();
    }

    public function test_soft_deleted_batch_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();
        $batch->delete();

        $this->get("/batches/{$batch->id}/startup-inspection")->assertNotFound();
    }

    public function test_valid_submission_persists_items_without_touching_batch_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $this->validItemsPayload()])
            ->assertRedirect("/batches/{$batch->id}/startup-check");

        $inspection = $batch->fresh()->startupInspection;
        $this->assertNotNull($inspection);
        $this->assertNotNull($inspection->completed_at);
        $this->assertSame(count(StartupInspectionItem::PARAMETER_KEYS), $inspection->items()->count());
        $this->assertSame(IpcBatch::STAGE_STARTUP, $batch->fresh()->current_stage);
    }

    public function test_samples_and_weight_master_box_can_be_left_blank(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $this->validItemsPayload()])
            ->assertRedirect("/batches/{$batch->id}/startup-check");

        $this->assertSame(0, $batch->fresh()->startupInspection->samples()->count());
    }

    public function test_a_partially_filled_sample_row_is_persisted(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $payload = [
            'items' => $this->validItemsPayload(),
            'samples' => [
                ['sample_no' => 1, 'volume_weight' => 10.5, 'weight_master_box' => null],
            ],
        ];

        $this->put("/batches/{$batch->id}/startup-inspection", $payload)->assertRedirect("/batches/{$batch->id}/startup-check");

        $sample = $batch->fresh()->startupInspection->samples()->first();
        $this->assertSame(1, $sample->sample_no);
        $this->assertEquals(10.5, (float) $sample->volume_weight);
        $this->assertNull($sample->weight_master_box);
    }

    public function test_test_result_toggle_is_persisted(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();
        $testType = $this->makeTestType();

        $payload = [
            'items' => $this->validItemsPayload(),
            'test_results' => [
                $testType->id => ['is_performed' => true],
            ],
        ];

        $this->put("/batches/{$batch->id}/startup-inspection", $payload)->assertRedirect("/batches/{$batch->id}/startup-check");

        $result = $batch->fresh()->startupInspection->testResults()->first();
        $this->assertSame($testType->id, $result->master_test_type_id);
        $this->assertTrue($result->is_performed);
    }

    public function test_missing_checklist_item_status_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $items = $this->validItemsPayload();
        unset($items['bulk_odor']);

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $items])
            ->assertSessionHasErrors('items.bulk_odor.status');

        $this->assertNull($batch->fresh()->startupInspection);
    }

    public function test_completed_inspection_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $this->validItemsPayload()])
            ->assertRedirect("/batches/{$batch->id}/startup-check");

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $this->validItemsPayload()])
            ->assertForbidden();
    }
}
