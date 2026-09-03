<?php

namespace Tests\Feature;

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

class StartupCheckTest extends TestCase
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

    private function validPayload(): array
    {
        $checklist = [];
        foreach (StartupCheck::checklistGroups() as $group) {
            $checklist = [...$checklist, ...array_fill_keys(array_keys($group['fields']), $group['options'][0])];
        }

        return [
            ...$checklist,
            'validation_report_status' => StartupCheck::VALIDATION_REPORT_READY,
            'filling_range_min' => 10,
            'filling_range_max' => 12,
            'density' => 1.05,
            'average_of_empty_bottle_weight' => 21.5,
            'heating' => 'Yes',
            'line_leader_name' => 'Budi',
            'operator_name' => 'Sari',
            'remarks' => 'OK',
        ];
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $batch = $this->makeBatch();

        $this->get("/batches/{$batch->id}/startup-check")->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_form(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->get("/batches/{$batch->id}/startup-check")->assertOk();
    }

    public function test_soft_deleted_batch_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();
        $batch->delete();

        $this->get("/batches/{$batch->id}/startup-check")->assertNotFound();
    }

    public function test_valid_submission_persists_check_and_advances_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->put("/batches/{$batch->id}/startup-check", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_FILLING, $batch->current_stage);

        $startupCheck = $batch->startupCheck()->first();
        $this->assertNotNull($startupCheck->completed_at);
        $this->assertSame(StartupCheck::STATUS_AVAILABLE, $startupCheck->product_standard_status);
        $this->assertEquals(21.5, (float) $startupCheck->average_of_empty_bottle_weight);
    }

    public function test_missing_checklist_status_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $payload = $this->validPayload();
        unset($payload['product_standard_status']);

        $this->put("/batches/{$batch->id}/startup-check", $payload)
            ->assertSessionHasErrors('product_standard_status');

        $this->assertNull($batch->fresh()->startupCheck);
    }

    public function test_missing_average_of_empty_bottle_weight_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $payload = $this->validPayload();
        unset($payload['average_of_empty_bottle_weight']);

        $this->put("/batches/{$batch->id}/startup-check", $payload)
            ->assertSessionHasErrors('average_of_empty_bottle_weight');

        $this->assertNull($batch->fresh()->startupCheck);
    }

    public function test_completed_startup_check_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $this->put("/batches/{$batch->id}/startup-check", $this->validPayload())->assertRedirect("/batches/{$batch->id}");

        $this->put("/batches/{$batch->id}/startup-check", $this->validPayload())->assertForbidden();
    }

    public function test_invalid_validation_report_status_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $payload = $this->validPayload();
        $payload['validation_report_status'] = 'Approved';

        $this->put("/batches/{$batch->id}/startup-check", $payload)
            ->assertSessionHasErrors('validation_report_status');

        $this->assertNull($batch->fresh()->startupCheck);
    }

    public function test_photo_can_be_uploaded_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $first = UploadedFile::fake()->image('color1.jpg');
        $this->post("/batches/{$batch->id}/startup-check/photo/color", ['photo' => $first])
            ->assertRedirect("/batches/{$batch->id}/startup-check");

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        $firstPath = IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->first()->file_path;
        Storage::disk('public')->assertExists($firstPath);

        $second = UploadedFile::fake()->image('color2.jpg');
        $this->post("/batches/{$batch->id}/startup-check/photo/color", ['photo' => $second]);

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_photo_upload_rejects_unknown_field(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/startup-check/photo/unknown", ['photo' => $photo])->assertNotFound();
    }

    public function test_photo_upload_forbidden_once_startup_check_is_completed(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatch();
        $this->put("/batches/{$batch->id}/startup-check", $this->validPayload());

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/startup-check/photo/color", ['photo' => $photo])->assertForbidden();
    }
}
