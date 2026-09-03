<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\StartupInspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    private function validPayload(array $overrides = []): array
    {
        $checklist = [];
        foreach (PackingCheck::checklistGroups() as $group) {
            $checklist = [...$checklist, ...array_fill_keys(array_keys($group['fields']), $group['options'][0])];
        }

        return [
            ...$checklist,
            'finalize' => true,
            'sum_weight_mb' => 105.0,
            'line_leader_name' => 'Budi',
            'coding_machine' => 'CM-01',
            'remarks' => 'OK',
            'decision' => PackingCheck::DECISION_PASSED,
            ...$overrides,
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
        $this->assertSame(1, $packingCheck->save_count);
    }

    public function test_draft_save_persists_partial_data_without_completing_or_advancing_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'remarks' => null, 'decision' => null]))
            ->assertRedirect("/batches/{$batch->id}/packing-check");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_PACKING, $batch->current_stage);

        $packingCheck = $batch->packingCheck()->first();
        $this->assertNull($packingCheck->completed_at);
        $this->assertSame(1, $packingCheck->save_count);
    }

    public function test_save_count_increments_across_draft_and_final_saves(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => true]));

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame(3, $packingCheck->save_count);
        $this->assertNotNull($packingCheck->completed_at);
        $this->assertCount(3, $packingCheck->revisions()->get());
    }

    public function test_line_leader_and_coding_machine_are_locked_after_the_first_save(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));

        // Round 2 sends neither field (the form stops asking once locked) — the round-1 values
        // must survive untouched, not be overwritten with blanks.
        $round2 = $this->validPayload(['finalize' => false]);
        unset($round2['line_leader_name'], $round2['coding_machine']);
        $this->put("/batches/{$batch->id}/packing-check", $round2);

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame('Budi', $packingCheck->line_leader_name);
        $this->assertSame('CM-01', $packingCheck->coding_machine);
    }

    public function test_standard_weight_mb_is_taken_from_the_batchs_start_inspection_samples(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by]);
        $inspection->samples()->create(['sample_no' => 1, 'weight_master_box' => 12.3456]);
        $inspection->samples()->create(['sample_no' => 2, 'weight_master_box' => 12.7]);
        $inspection->samples()->create(['sample_no' => 3, 'weight_master_box' => null]);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => true]));

        $this->assertSame('12.7000', (string) $batch->fresh()->packingCheck->standard_weight_mb);
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

    public function test_every_checklist_item_accepts_na_not_just_secondary_coding(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $payload = $this->validPayload();
        $payload['primary_bulk_status'] = PackingCheck::STATUS_NA;
        $payload['tersier_identity_status'] = PackingCheck::STATUS_NA;

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertRedirect("/batches/{$batch->id}");

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame(PackingCheck::STATUS_NA, $packingCheck->primary_bulk_status);
        $this->assertSame(PackingCheck::STATUS_NA, $packingCheck->tersier_identity_status);
    }

    public function test_completed_packing_check_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())->assertRedirect("/batches/{$batch->id}");

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())->assertForbidden();
    }

    public function test_photo_can_be_uploaded_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $first = UploadedFile::fake()->image('color1.jpg');
        $this->post("/batches/{$batch->id}/packing-check/photo/color", ['photo' => $first])
            ->assertRedirect("/batches/{$batch->id}/packing-check");

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        $firstPath = IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->first()->file_path;
        Storage::disk('public')->assertExists($firstPath);

        $second = UploadedFile::fake()->image('color2.jpg');
        $this->post("/batches/{$batch->id}/packing-check/photo/color", ['photo' => $second]);

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_photo_upload_rejects_unknown_field(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/packing-check/photo/unknown", ['photo' => $photo])->assertNotFound();
    }

    public function test_photo_upload_forbidden_once_packing_check_is_completed(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload());

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/packing-check/photo/color", ['photo' => $photo])->assertForbidden();
    }
}
