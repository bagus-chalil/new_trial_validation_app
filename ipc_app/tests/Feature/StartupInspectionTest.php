<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterTestType;
use App\Models\PackingCheck;
use App\Models\StartupInspection;
use App\Models\StartupInspectionItem;
use App\Models\StartupInspectionSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartupInspectionTest extends TestCase
{
    use RefreshDatabase;

    // Defaults to the currently-acted-as user so every pre-existing test keeps meaning "owner
    // edits their own batch"; pass an explicit id to build a not-the-owner (403) case instead.
    private function makeBatch(?int $createdBy = null): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $createdBy ?? auth()->id() ?? User::factory()->create()->id,
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

    private function validSamplesPayload(): array
    {
        return array_map(
            fn (int $n) => ['sample_no' => $n, 'volume_weight' => round(10 + $n / 10, 2), 'weight_master_box' => null],
            range(1, StartupInspectionSample::SAMPLE_COUNT),
        );
    }

    private function validPayload(): array
    {
        return ['items' => $this->validItemsPayload(), 'samples' => $this->validSamplesPayload()];
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

        $this->put("/batches/{$batch->id}/startup-inspection", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}/startup-check");

        $inspection = $batch->fresh()->startupInspection;
        $this->assertNotNull($inspection);
        $this->assertNotNull($inspection->completed_at);
        $this->assertSame(count(StartupInspectionItem::PARAMETER_KEYS), $inspection->items()->count());
        $this->assertSame(IpcBatch::STAGE_STARTUP, $batch->fresh()->current_stage);
    }

    public function test_weight_master_box_can_be_left_blank(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->put("/batches/{$batch->id}/startup-inspection", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}/startup-check");

        $samples = $batch->fresh()->startupInspection->samples()->orderBy('sample_no')->get();
        $this->assertCount(StartupInspectionSample::SAMPLE_COUNT, $samples);
        $this->assertEquals(10.1, (float) $samples->first()->volume_weight);
        $this->assertNull($samples->first()->weight_master_box);
    }

    public function test_missing_volume_weight_samples_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $samples = $this->validSamplesPayload();
        $samples[29]['volume_weight'] = null;

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $this->validItemsPayload(), 'samples' => $samples])
            ->assertSessionHasErrors('samples');

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $this->validItemsPayload()])
            ->assertSessionHasErrors('samples');

        $this->assertNull($batch->fresh()->startupInspection);
    }

    public function test_sample_values_allow_at_most_two_decimal_places(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $samples = $this->validSamplesPayload();
        $samples[0]['volume_weight'] = 10.123;
        $samples[1]['weight_master_box'] = 5.5555;

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $this->validItemsPayload(), 'samples' => $samples])
            ->assertSessionHasErrors(['samples.0.volume_weight', 'samples.1.weight_master_box']);

        $this->assertNull($batch->fresh()->startupInspection);
    }

    public function test_test_result_toggle_is_persisted(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();
        $testType = $this->makeTestType();

        $payload = [
            ...$this->validPayload(),
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

        $this->put("/batches/{$batch->id}/startup-inspection", ['items' => $items, 'samples' => $this->validSamplesPayload()])
            ->assertSessionHasErrors('items.bulk_odor.status');

        $this->assertNull($batch->fresh()->startupInspection);
    }

    public function test_completed_inspection_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->put("/batches/{$batch->id}/startup-inspection", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}/startup-check");

        $this->put("/batches/{$batch->id}/startup-inspection", $this->validPayload())
            ->assertForbidden();
    }

    /**
     * A batch whose Start Inspection is completed (with Volume/Weight samples but no Weight
     * Master Box) and whose Filling Check has a save — i.e. Packing is open.
     */
    private function makeBatchAtPacking(): IpcBatch
    {
        $batch = $this->makeBatch();
        $batch->update(['current_stage' => IpcBatch::STAGE_PACKING]);

        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'completed_at' => now()]);
        foreach (range(1, StartupInspectionSample::SAMPLE_COUNT) as $n) {
            $inspection->samples()->create(['sample_no' => $n, 'volume_weight' => 10]);
        }

        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by]);

        return $batch->fresh();
    }

    private function masterBoxPayload(array $weights): array
    {
        return ['samples' => array_map(
            fn (int $n) => ['sample_no' => $n, 'weight_master_box' => $weights[$n] ?? null],
            range(1, StartupInspectionSample::SAMPLE_COUNT),
        )];
    }

    public function test_master_box_can_be_filled_once_from_packing(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchAtPacking();

        $this->get("/batches/{$batch->id}/startup-inspection")
            ->assertInertia(fn ($page) => $page->where('masterBoxOnly', true)->where('isReadOnly', true));

        $this->put("/batches/{$batch->id}/startup-inspection/master-box", $this->masterBoxPayload([1 => 250.5, 2 => 251]))
            ->assertRedirect("/batches/{$batch->id}/packing-check");

        $samples = $batch->startupInspection->samples()->orderBy('sample_no')->get();
        $this->assertSame('250.50', $samples[0]->weight_master_box);
        $this->assertSame('251.00', $samples[1]->weight_master_box);
        // Volume/Weight is untouched by the late fill.
        $this->assertSame('10.00', $samples[0]->volume_weight);

        // One-time: a second fill is refused and the page is back to fully read-only.
        $this->put("/batches/{$batch->id}/startup-inspection/master-box", $this->masterBoxPayload([3 => 260]))
            ->assertForbidden();
        $this->assertNull($samples[2]->fresh()->weight_master_box);
        $this->get("/batches/{$batch->id}/startup-inspection")
            ->assertInertia(fn ($page) => $page->where('masterBoxOnly', false));
    }

    public function test_master_box_filled_at_start_inspection_cannot_be_filled_again(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchAtPacking();
        $batch->startupInspection->samples()->where('sample_no', 1)->update(['weight_master_box' => 100]);

        $this->put("/batches/{$batch->id}/startup-inspection/master-box", $this->masterBoxPayload([2 => 260]))
            ->assertForbidden();
    }

    public function test_master_box_cannot_be_filled_before_filling_check_is_saved(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchAtPacking();
        $batch->fillingCheck->delete();

        $this->put("/batches/{$batch->id}/startup-inspection/master-box", $this->masterBoxPayload([1 => 250]))
            ->assertForbidden();
    }

    public function test_master_box_cannot_be_filled_after_packing_is_finalized(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchAtPacking();
        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'completed_at' => now()]);

        $this->put("/batches/{$batch->id}/startup-inspection/master-box", $this->masterBoxPayload([1 => 250]))
            ->assertForbidden();
    }

    public function test_master_box_fill_requires_at_least_one_value(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchAtPacking();

        $this->put("/batches/{$batch->id}/startup-inspection/master-box", $this->masterBoxPayload([]))
            ->assertSessionHasErrors('samples');
    }

    public function test_approver_cannot_fill_master_box(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchAtPacking();
        $this->actingAs(User::factory()->approver()->create());

        $this->put("/batches/{$batch->id}/startup-inspection/master-box", $this->masterBoxPayload([1 => 250]))
            ->assertForbidden();
    }
}
