<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FillingCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchWithCompletedStartupCheck(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_FILLING,
        ]);

        StartupCheck::create([
            'ipc_batch_id' => $batch->id,
            'user_id' => $batch->created_by,
            'density' => 1.0,
            'average_of_empty_bottle_weight' => 20.0,
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    private function validPayload(bool $finalize = true): array
    {
        return [
            'finalize' => $finalize,
            'sample_bulk_odor_status' => 'Conform',
            'sample_leakage_test_status' => 'Conform',
            'remarks' => 'OK',
            'decision' => FillingCheck::DECISION_PASSED,
            'samples' => array_map(
                fn (int $sampleNo) => ['sample_no' => $sampleNo, 'weight_value' => 20 + $sampleNo],
                range(1, 10),
            ),
        ];
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->get("/batches/{$batch->id}/filling-check")->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_form(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->get("/batches/{$batch->id}/filling-check")->assertOk();
    }

    public function test_form_is_forbidden_when_startup_check_is_not_completed(): void
    {
        $this->actingAs(User::factory()->create());
        $product = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'bulk_code' => 'BULK-2', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 02', 'name' => 'Make Up 02', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-002',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);

        $this->get("/batches/{$batch->id}/filling-check")->assertForbidden();
    }

    public function test_soft_deleted_batch_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();
        $batch->delete();

        $this->get("/batches/{$batch->id}/filling-check")->assertNotFound();
    }

    public function test_valid_submission_computes_weight_results_and_advances_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->put("/batches/{$batch->id}/filling-check", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_PACKING, $batch->current_stage);

        $fillingCheck = $batch->fillingCheck()->with('samples')->first();
        $this->assertNotNull($fillingCheck->completed_at);
        $this->assertCount(10, $fillingCheck->samples);

        // density=1.0, average_of_empty_bottle_weight=20.0 -> result = weight - 20.
        $sample1 = $fillingCheck->samples->firstWhere('sample_no', 1);
        $this->assertEquals(1.0, (float) $sample1->weight_result);
        $sample10 = $fillingCheck->samples->firstWhere('sample_no', 10);
        $this->assertEquals(10.0, (float) $sample10->weight_result);

        // average of results 1..10 = 5.5
        $this->assertEquals(5.5, (float) $fillingCheck->average_weight);
    }

    public function test_missing_field_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $payload = $this->validPayload();
        unset($payload['decision']);

        $this->put("/batches/{$batch->id}/filling-check", $payload)
            ->assertSessionHasErrors('decision');

        $this->assertNull($batch->fresh()->fillingCheck);
    }

    public function test_incomplete_weight_samples_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $payload = $this->validPayload();
        $payload['samples'] = [['sample_no' => 1, 'weight_value' => 21]];

        $this->put("/batches/{$batch->id}/filling-check", $payload)
            ->assertSessionHasErrors('samples');
    }

    public function test_completed_filling_check_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->put("/batches/{$batch->id}/filling-check", $this->validPayload())->assertRedirect("/batches/{$batch->id}");

        $this->put("/batches/{$batch->id}/filling-check", $this->validPayload())->assertForbidden();
    }

    public function test_draft_save_allows_partial_data_without_completing_or_advancing_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->put("/batches/{$batch->id}/filling-check", [
            'finalize' => false,
            'samples' => [['sample_no' => 1, 'weight_value' => 21]],
        ])->assertRedirect("/batches/{$batch->id}/filling-check");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_FILLING, $batch->current_stage);

        $fillingCheck = $batch->fillingCheck()->with('samples')->first();
        $this->assertNull($fillingCheck->completed_at);
        $this->assertSame(1, $fillingCheck->save_count);
        $this->assertCount(1, $fillingCheck->samples);
    }

    public function test_th_progress_increments_across_repeated_draft_saves_and_the_final_save(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->put("/batches/{$batch->id}/filling-check", ['finalize' => false, 'samples' => []]);
        $this->put("/batches/{$batch->id}/filling-check", ['finalize' => false, 'samples' => []]);
        $this->put("/batches/{$batch->id}/filling-check", $this->validPayload(finalize: true));

        $fillingCheck = $batch->fresh()->fillingCheck;
        $this->assertSame(3, $fillingCheck->save_count);
        $this->assertNotNull($fillingCheck->completed_at);
    }

    public function test_each_save_writes_its_own_revision_preserving_earlier_remarks(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->put("/batches/{$batch->id}/filling-check", [
            'finalize' => false,
            'remarks' => 'Hour 1 check, slightly under target',
            'samples' => [['sample_no' => 1, 'weight_value' => 21]],
        ]);
        $this->put("/batches/{$batch->id}/filling-check", [
            'finalize' => false,
            'remarks' => 'Hour 2 check, back within range',
            'samples' => [['sample_no' => 1, 'weight_value' => 22]],
        ]);

        $fillingCheck = $batch->fresh()->fillingCheck()->with('revisions.samples')->first();
        $this->assertCount(2, $fillingCheck->revisions);

        $revision1 = $fillingCheck->revisions->firstWhere('revision_no', 1);
        $revision2 = $fillingCheck->revisions->firstWhere('revision_no', 2);

        $this->assertSame('Hour 1 check, slightly under target', $revision1->remarks);
        $this->assertSame('Hour 2 check, back within range', $revision2->remarks);
        $this->assertEquals(21.0, (float) $revision1->samples->firstWhere('sample_no', 1)->weight_value);
        $this->assertEquals(22.0, (float) $revision2->samples->firstWhere('sample_no', 1)->weight_value);

        // The "current" filling_checks row only reflects the latest save — the point of the
        // revisions table is that the earlier remarks/samples above are NOT lost, even though
        // this row itself has moved on.
        $this->assertSame('Hour 2 check, back within range', $fillingCheck->remarks);
    }

    public function test_draft_save_ignores_the_all_ten_samples_requirement(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $this->put("/batches/{$batch->id}/filling-check", [
            'finalize' => false,
            'samples' => [['sample_no' => 1, 'weight_value' => 21]],
        ])->assertSessionDoesntHaveErrors();
    }

    public function test_color_photo_can_be_uploaded_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();

        $first = UploadedFile::fake()->image('color1.jpg');
        $this->post("/batches/{$batch->id}/filling-check/color-photo", ['photo' => $first])
            ->assertRedirect("/batches/{$batch->id}/filling-check");

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        $firstPath = IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->first()->file_path;
        Storage::disk('public')->assertExists($firstPath);

        $second = UploadedFile::fake()->image('color2.jpg');
        $this->post("/batches/{$batch->id}/filling-check/color-photo", ['photo' => $second]);

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_color_photo_upload_forbidden_once_filling_check_is_completed(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedStartupCheck();
        $this->put("/batches/{$batch->id}/filling-check", $this->validPayload());

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/filling-check/color-photo", ['photo' => $photo])->assertForbidden();
    }
}
