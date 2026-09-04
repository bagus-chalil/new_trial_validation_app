<?php

namespace Tests\Feature;

use App\Http\Controllers\FinishedCheckController;
use App\Models\FinishedCheckSample;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinishedCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchWithCompletedPackingCheck(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_FINISHED,
        ]);

        PackingCheck::create([
            'ipc_batch_id' => $batch->id,
            'user_id' => $batch->created_by,
            'decision' => PackingCheck::DECISION_PASSED,
            'save_count' => 1,
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    private function seedFinishedCheckPhotos(IpcBatch $batch): void
    {
        foreach (FinishedCheckController::PHOTO_FIELDS as $field) {
            IpcAttachment::create([
                'ipc_batch_id' => $batch->id,
                'stage' => 'finished',
                'field_label' => $field,
                'file_path' => "ipc-attachments/{$batch->id}/finished/{$field}.jpg",
                'uploaded_by' => $batch->created_by,
            ]);
        }
    }

    /**
     * Every one of the 19 AQL parameter rows needs at least one of AC/CD/MD/mD filled on
     * finalize (see SaveFinishedCheckRequest::validateFinalizeSampleRows()) — so a "valid"
     * payload for finalize tests must give each row a value, not just a couple of them.
     */
    private function validSamples(): array
    {
        return collect(FinishedCheckSample::PARAMETER_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => ['ac' => 1, 'cd' => 0, 'md' => 0, 'mnd' => 0]])
            ->all();
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'finalize' => true,
            'quantity_wi' => 500,
            'masterbox' => 50,
            'no_pallet_qty' => 5,
            'quantity_sampling_aql' => 32,
            'quantity_sample_aql_cd' => 0,
            'quantity_sample_aql_md' => 1,
            'quantity_sample_aql_mnd' => 2,
            'quantity_special_inspection' => 10,
            'quantity_special_inspection_cd' => 0,
            'quantity_special_inspection_md' => 0,
            'quantity_special_inspection_mnd' => 0,
            'line_leader_name' => 'Budi',
            'disposition' => 'Accepted',
            'remarks' => 'OK',
            'samples' => $this->validSamples(),
            ...$overrides,
        ];
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $this->get("/batches/{$batch->id}/finished-check")->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_form(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $this->get("/batches/{$batch->id}/finished-check")->assertOk();
    }

    public function test_form_is_forbidden_when_packing_check_is_not_completed(): void
    {
        $this->actingAs(User::factory()->create());
        $product = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'bulk_code' => 'BULK-2', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 02', 'name' => 'Make Up 02', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-002',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_PACKING,
        ]);

        $this->get("/batches/{$batch->id}/finished-check")->assertForbidden();
    }

    public function test_soft_deleted_batch_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();
        $batch->delete();

        $this->get("/batches/{$batch->id}/finished-check")->assertNotFound();
    }

    /**
     * Draft saves let individual fields go blank — QC can record one round and come back later,
     * same as Packing/Filling Check — but a save with literally nothing filled in is rejected
     * (found live 2026-09-04: a fully blank "Simpan" was silently persisting an empty row).
     */
    public function test_completely_blank_draft_save_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $this->put("/batches/{$batch->id}/finished-check", ['finalize' => false])
            ->assertSessionHasErrors('progress');

        $this->assertNull($batch->fresh()->finishedCheck);
    }

    public function test_draft_save_does_not_advance_stage_or_complete(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $this->put("/batches/{$batch->id}/finished-check", ['finalize' => false, 'quantity_wi' => 100]);

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->current_stage);
        $this->assertNull($batch->finishedCheck->completed_at);
        $this->assertSame('100.00', $batch->finishedCheck->quantity_wi);
    }

    /**
     * Direct live-testing feedback from the user: clicking Save & End on a blank form went
     * straight through to the next stage. This is the load-bearing proof that it no longer does.
     */
    public function test_finalize_on_a_blank_form_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $this->put("/batches/{$batch->id}/finished-check", ['finalize' => true])
            ->assertSessionHasErrors([
                'quantity_wi', 'masterbox', 'no_pallet_qty',
                'quantity_sampling_aql', 'quantity_sample_aql_cd', 'quantity_sample_aql_md', 'quantity_sample_aql_mnd',
                'quantity_special_inspection', 'quantity_special_inspection_cd', 'quantity_special_inspection_md', 'quantity_special_inspection_mnd',
                'line_leader_name', 'disposition', 'remarks',
                'photo_wi_number', 'photo_exp_date', 'photo_color',
                'samples.tersier_identity', 'samples.functional_test',
            ]);

        $this->assertNull($batch->fresh()->finishedCheck);
    }

    public function test_finalize_without_photos_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $this->put("/batches/{$batch->id}/finished-check", $this->validPayload())
            ->assertSessionHasErrors(['photo_wi_number', 'photo_exp_date', 'photo_color']);

        $this->assertNull($batch->fresh()->finishedCheck);
    }

    /**
     * Direct user feedback 2026-09-04: the header quantity fields turned red on a failed
     * Selesaikan while the 19-row AQL sample grid stayed collapsed and unvalidated — an
     * inconsistent "required" story. Each row now needs at least one of AC/CD/MD/mD filled.
     */
    public function test_finalize_rejects_a_completely_blank_sample_row(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();
        $this->seedFinishedCheckPhotos($batch);

        $samples = $this->validSamples();
        $samples['tersier_appearance'] = ['ac' => null, 'cd' => null, 'md' => null, 'mnd' => null];

        $this->put("/batches/{$batch->id}/finished-check", $this->validPayload(['samples' => $samples]))
            ->assertSessionHasErrors(['samples.tersier_appearance']);

        $this->assertNull($batch->fresh()->finishedCheck);
    }

    public function test_finalize_persists_header_and_samples_and_advances_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();
        $this->seedFinishedCheckPhotos($batch);

        $samples = $this->validSamples();
        $samples['tersier_identity'] = ['ac' => 13, 'cd' => 0, 'md' => 0, 'mnd' => 0];
        $samples['functional_test'] = ['ac' => 5, 'cd' => 0, 'md' => 1, 'mnd' => 0];

        $this->put("/batches/{$batch->id}/finished-check", $this->validPayload(['samples' => $samples]))
            ->assertRedirect("/batches/{$batch->id}");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->current_stage);

        $finishedCheck = $batch->finishedCheck()->first();
        $this->assertNotNull($finishedCheck->completed_at);
        $this->assertSame('Accepted', $finishedCheck->disposition);
        $this->assertSame('500.00', $finishedCheck->quantity_wi);

        $tersierIdentity = $finishedCheck->samples()->where('parameter_key', 'tersier_identity')->first();
        $this->assertSame(13, $tersierIdentity->ac);

        $functionalTest = $finishedCheck->samples()->where('parameter_key', 'functional_test')->first();
        $this->assertSame(1, $functionalTest->md);

        // Every one of the 19 groups gets a row.
        $this->assertCount(count(FinishedCheckSample::PARAMETER_KEYS), $finishedCheck->samples);
    }

    /**
     * Every Save/Save & End writes an immutable finished_check_revisions row, same shape as
     * Filling/Packing Check's own "Riwayat Simpan" — added after the user pointed out Finished
     * Check was the only save-capable stage missing this history.
     */
    public function test_each_save_writes_a_revision_and_increments_save_count(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();
        $this->seedFinishedCheckPhotos($batch);

        $this->put("/batches/{$batch->id}/finished-check", ['finalize' => false, 'quantity_wi' => 100]);
        $this->put("/batches/{$batch->id}/finished-check", $this->validPayload());

        $finishedCheck = $batch->fresh()->finishedCheck;
        $this->assertSame(2, $finishedCheck->save_count);
        $this->assertCount(2, $finishedCheck->revisions);

        $firstRevision = $finishedCheck->revisions()->where('revision_no', 1)->first();
        $this->assertFalse($firstRevision->finalize);
        $this->assertSame('100.00', $firstRevision->quantity_wi);
        $this->assertNull($firstRevision->disposition);

        $secondRevision = $finishedCheck->revisions()->where('revision_no', 2)->first();
        $this->assertTrue($secondRevision->finalize);
        $this->assertSame('Accepted', $secondRevision->disposition);
        $this->assertCount(count(FinishedCheckSample::PARAMETER_KEYS), $secondRevision->samples);
    }

    public function test_completed_finished_check_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();
        $this->seedFinishedCheckPhotos($batch);

        $this->put("/batches/{$batch->id}/finished-check", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}");

        $this->put("/batches/{$batch->id}/finished-check", $this->validPayload())->assertForbidden();
    }

    public function test_photo_can_be_uploaded_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $first = UploadedFile::fake()->image('color1.jpg');
        $this->post("/batches/{$batch->id}/finished-check/photo/color", ['photo' => $first])
            ->assertRedirect("/batches/{$batch->id}/finished-check");

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        $firstPath = IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->first()->file_path;
        Storage::disk('public')->assertExists($firstPath);

        $second = UploadedFile::fake()->image('color2.jpg');
        $this->post("/batches/{$batch->id}/finished-check/photo/color", ['photo' => $second]);

        $this->assertSame(1, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_photo_upload_rejects_unknown_field(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/finished-check/photo/unknown", ['photo' => $photo])->assertNotFound();
    }

    public function test_photo_upload_forbidden_once_finished_check_is_completed(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedPackingCheck();
        $this->seedFinishedCheckPhotos($batch);
        $this->put("/batches/{$batch->id}/finished-check", $this->validPayload());

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/finished-check/photo/color", ['photo' => $photo])->assertForbidden();
    }
}
