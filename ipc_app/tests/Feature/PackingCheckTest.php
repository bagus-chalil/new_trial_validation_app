<?php

namespace Tests\Feature;

use App\Http\Controllers\PackingCheckController;
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

    // Defaults to the currently-acted-as user so every pre-existing test keeps meaning "owner
    // edits their own batch"; pass an explicit id to build a not-the-owner (403) case instead.
    private function makeBatchWithCompletedFillingCheck(?int $createdBy = null): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $createdBy ?? auth()->id() ?? User::factory()->create()->id,
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

    /**
     * Finalize (Simpan & Selesaikan) requires all 5 packing photo fields, which don't travel
     * through the packing-check payload itself, so tests that finalize successfully must seed
     * them directly rather than via validPayload().
     */
    private function seedPackingFinalizePrereqs(IpcBatch $batch): void
    {
        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by]);
        $inspection->samples()->create(['sample_no' => 1, 'weight_master_box' => 2600]);

        $this->seedPackingPhotos($batch);
    }

    private function seedPackingPhotos(IpcBatch $batch): void
    {
        foreach (PackingCheckController::PHOTO_FIELDS as $field) {
            IpcAttachment::create([
                'ipc_batch_id' => $batch->id,
                'stage' => 'packing',
                'field_label' => $field,
                'file_path' => "ipc-attachments/{$batch->id}/packing/{$field}.jpg",
                'uploaded_by' => $batch->created_by,
            ]);
        }
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
            'standard_weight_mb' => '1920-2000',
            'line_leader_name' => 'Budi',
            'coding_machine' => 'CM-01',
            'weighing_data' => 'Ada',
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

    public function test_form_is_forbidden_when_filling_check_has_never_been_saved(): void
    {
        $this->actingAs(User::factory()->create());
        $product = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'is_active' => true]);
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

    public function test_form_opens_once_filling_has_a_draft_save_but_finalize_needs_filling_finalized(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $batch->fillingCheck->update(['completed_at' => null, 'save_count' => 1]);
        $batch->update(['current_stage' => IpcBatch::STAGE_FILLING]);
        $this->seedPackingFinalizePrereqs($batch);

        $this->get("/batches/{$batch->id}/packing-check")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isReadOnly', false)->where('previousStageCompleted', false));

        // A round can be recorded while Filling is still open...
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]))
            ->assertSessionDoesntHaveErrors();

        // ...but Selesaikan waits for Filling to be finalized.
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())
            ->assertSessionHasErrors('progress');
        $this->assertNull($batch->fresh()->packingCheck->completed_at);

        $batch->fillingCheck->update(['completed_at' => now()]);
        $batch->update(['current_stage' => IpcBatch::STAGE_PACKING]);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())
            ->assertSessionDoesntHaveErrors();
        $this->assertNotNull($batch->fresh()->packingCheck->completed_at);
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);
    }

    public function test_draft_is_rejected_on_the_last_allowed_th_progress_round(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        PackingCheck::create([
            'ipc_batch_id' => $batch->id,
            'user_id' => $batch->created_by,
            'save_count' => IpcBatch::MAX_TH_PROGRESS - 1,
        ]);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]))
            ->assertSessionHasErrors('progress');
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
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())
            ->assertRedirect("/batches/{$batch->id}/finished-check");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->current_stage);

        $packingCheck = $batch->packingCheck()->first();
        $this->assertNotNull($packingCheck->completed_at);
        $this->assertSame(PackingCheck::STATUS_CONFORM, $packingCheck->primary_bulk_status);
        $this->assertSame(PackingCheck::DECISION_PASSED, $packingCheck->decision);
        $this->assertSame(1, $packingCheck->save_count);
    }

    public function test_completely_blank_draft_save_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", ['finalize' => false])
            ->assertSessionHasErrors('progress');

        $this->assertNull($batch->fresh()->packingCheck);
    }

    public function test_draft_save_persists_partial_data_without_completing_or_advancing_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'remarks' => null, 'decision' => null, 'standard_weight_mb' => null]))
            ->assertRedirect("/batches/{$batch->id}/finished-check");

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_PACKING, $batch->current_stage);

        $packingCheck = $batch->packingCheck()->first();
        $this->assertNull($packingCheck->completed_at);
        $this->assertSame(1, $packingCheck->save_count);
    }

    public function test_draft_save_resets_checklist_sum_weight_remarks_and_decision_for_the_next_round(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));

        // The live row must come back blank for round 2 — otherwise reloading the page (not just
        // the in-session React state) would look like editing round 1's answers instead of
        // starting a fresh round.
        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertNull($packingCheck->primary_bulk_status);
        $this->assertNull($packingCheck->secondary_coding_na_status);
        $this->assertNull($packingCheck->tersier_coding_na_status);
        $this->assertNull($packingCheck->primary_capping_sealing_status);
        $this->assertNull($packingCheck->tersier_shipper_label_status);
        $this->assertNull($packingCheck->sum_weight_mb);
        $this->assertNull($packingCheck->remarks);
        $this->assertNull($packingCheck->decision);

        // ...but line leader/coding machine (asked once, locked) must survive.
        $this->assertSame('Budi', $packingCheck->line_leader_name);
        $this->assertSame('CM-01', $packingCheck->coding_machine);
        $this->assertSame('Ada', $packingCheck->weighing_data);

        // And round 1's real answers must still be fully intact in its revision snapshot.
        $revision = $packingCheck->revisions()->where('revision_no', 1)->firstOrFail();
        $this->assertSame(PackingCheck::STATUS_CONFORM, $revision->primary_bulk_status);
        $this->assertSame('105.0000', (string) $revision->sum_weight_mb);
        $this->assertSame('OK', $revision->remarks);
        $this->assertSame(PackingCheck::DECISION_PASSED, $revision->decision);
    }

    public function test_draft_save_requires_weight_of_mb_every_round(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'sum_weight_mb' => null]))
            ->assertSessionHasErrors('sum_weight_mb');
        $this->assertNull($batch->fresh()->packingCheck);

        // Round 2 needs its own weighing too — round 1's value isn't carried forward.
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'sum_weight_mb' => null]))
            ->assertSessionHasErrors('sum_weight_mb');
        $this->assertSame(1, $batch->fresh()->packingCheck->save_count);
    }

    public function test_finalize_save_does_not_reset_the_final_round(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload());

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame(PackingCheck::STATUS_CONFORM, $packingCheck->primary_bulk_status);
        $this->assertSame('105.0000', (string) $packingCheck->sum_weight_mb);
        $this->assertSame('OK', $packingCheck->remarks);
        $this->assertSame(PackingCheck::DECISION_PASSED, $packingCheck->decision);
    }

    public function test_save_count_increments_across_draft_and_final_saves(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => true]));

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame(3, $packingCheck->save_count);
        $this->assertNotNull($packingCheck->completed_at);
        $this->assertCount(3, $packingCheck->revisions()->get());
    }

    public function test_each_save_snapshots_the_photo_current_at_that_moment(): void
    {
        // Confirms the fix for the "packing kok gk sesuai TH Progress" report — each round's
        // PackingCheckRevisionPhoto should freeze whichever photo was current when that round
        // was saved, and a later re-upload must not retroactively change an earlier round's
        // already-saved snapshot.
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $round1Photo = IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->first();
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));

        $secondUpload = UploadedFile::fake()->image('color-round2.jpg');
        $this->post("/batches/{$batch->id}/packing-check/photo/color", ['photo' => $secondUpload]);
        $round2Photo = IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->latest('id')->first();
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => true]));

        $packingCheck = $batch->fresh()->packingCheck;
        $revision1 = $packingCheck->revisions()->where('revision_no', 1)->firstOrFail();
        $revision2 = $packingCheck->revisions()->where('revision_no', 2)->firstOrFail();

        $this->assertSame($round1Photo->file_path, $revision1->photos()->where('field_label', 'color')->first()->file_path);
        $this->assertSame($round2Photo->file_path, $revision2->photos()->where('field_label', 'color')->first()->file_path);
        $this->assertNotSame($revision1->photos()->where('field_label', 'color')->first()->file_path, $revision2->photos()->where('field_label', 'color')->first()->file_path);
    }

    public function test_line_leader_and_coding_machine_are_locked_after_the_first_save(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));

        // Round 2 sends neither field (the form stops asking once locked) — the round-1 values
        // must survive untouched, not be overwritten with blanks.
        $round2 = $this->validPayload(['finalize' => false]);
        unset($round2['line_leader_name'], $round2['coding_machine'], $round2['standard_weight_mb']);
        $this->put("/batches/{$batch->id}/packing-check", $round2);

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame('Budi', $packingCheck->line_leader_name);
        $this->assertSame('CM-01', $packingCheck->coding_machine);
    }

    public function test_standard_weight_mb_is_typed_once_and_locked_after(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        // Start Inspection weights no longer feed this field.
        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by]);
        $inspection->samples()->create(['sample_no' => 1, 'weight_master_box' => 2610]);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));
        $this->assertSame('1920-2000', $batch->fresh()->packingCheck->standard_weight_mb);

        // Round 2 tries to change it — the round-1 value must survive.
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'standard_weight_mb' => 50]));
        $this->assertSame('1920-2000', $batch->fresh()->packingCheck->standard_weight_mb);
    }

    /** QC writes Std Bruto MB as a range (user, 2026-10-02); spaces around the dash are dropped. */
    public function test_standard_weight_mb_accepts_a_range_or_single_value_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'standard_weight_mb' => '1330 abc']))
            ->assertSessionHasErrors('standard_weight_mb');
        $this->assertNull($batch->fresh()->packingCheck);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'standard_weight_mb' => ' 1330 - 1410 ']))
            ->assertSessionHasNoErrors();
        $this->assertSame('1330-1410', $batch->fresh()->packingCheck->standard_weight_mb);
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

    public function test_missing_line_leader_name_is_rejected_on_first_finalize(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $payload = $this->validPayload();
        unset($payload['line_leader_name']);

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertSessionHasErrors('line_leader_name');

        $this->assertNull($batch->fresh()->packingCheck);
    }

    public function test_missing_coding_machine_is_rejected_on_first_finalize(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $payload = $this->validPayload();
        unset($payload['coding_machine']);

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertSessionHasErrors('coding_machine');

        $this->assertNull($batch->fresh()->packingCheck);
    }

    public function test_missing_photo_is_rejected_on_finalize(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);
        IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->delete();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())
            ->assertSessionHasErrors('photo_color');

        $this->assertNull($batch->fresh()->packingCheck);
    }

    public function test_missing_standard_weight_mb_is_rejected_on_first_finalize(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingPhotos($batch);

        $payload = $this->validPayload();
        unset($payload['standard_weight_mb']);

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertSessionHasErrors('standard_weight_mb');

        $this->assertNull($batch->fresh()->packingCheck);
    }

    public function test_draft_save_does_not_require_photos_or_standard_weight_mb(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'remarks' => null, 'decision' => null, 'standard_weight_mb' => null]))
            ->assertSessionDoesntHaveErrors(['standard_weight_mb', 'photo_palletisasi', 'photo_color']);
    }

    public function test_line_leader_and_coding_machine_not_required_once_already_locked(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false]));

        // Round 2 omits both fields entirely, same as the "form stops asking once locked" test
        // above, but this time finalizing — must not be rejected as missing.
        $round2 = $this->validPayload(['finalize' => true]);
        unset($round2['line_leader_name'], $round2['coding_machine'], $round2['standard_weight_mb']);
        $this->put("/batches/{$batch->id}/packing-check", $round2)
            ->assertSessionDoesntHaveErrors(['line_leader_name', 'coding_machine', 'standard_weight_mb']);

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertNotNull($packingCheck->completed_at);
        $this->assertSame('Budi', $packingCheck->line_leader_name);
        $this->assertSame('CM-01', $packingCheck->coding_machine);
    }

    public function test_secondary_coding_na_accepts_the_tri_state_value(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $payload = $this->validPayload();
        $payload['secondary_coding_na_status'] = PackingCheck::STATUS_NA;

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertRedirect("/batches/{$batch->id}/finished-check");

        $this->assertSame(PackingCheck::STATUS_NA, $batch->fresh()->packingCheck->secondary_coding_na_status);
    }

    public function test_every_checklist_item_accepts_na_not_just_secondary_coding(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $payload = $this->validPayload();
        $payload['primary_bulk_status'] = PackingCheck::STATUS_NA;
        $payload['tersier_identity_status'] = PackingCheck::STATUS_NA;

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertRedirect("/batches/{$batch->id}/finished-check");

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame(PackingCheck::STATUS_NA, $packingCheck->primary_bulk_status);
        $this->assertSame(PackingCheck::STATUS_NA, $packingCheck->tersier_identity_status);
    }

    public function test_completed_packing_check_is_read_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())->assertRedirect("/batches/{$batch->id}/finished-check");

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload())->assertForbidden();
    }

    public function test_photo_can_be_uploaded_multiple_times_and_accumulates_history(): void
    {
        // Unlike every other stage's photo upload, packing deliberately does NOT delete/replace
        // the previous row — it goes through repeatable TH_PROGRESS rounds, and the printed
        // report needs each round's own photo (see SavePackingCheck's revision-photo snapshot).
        // So a re-upload appends a new row and keeps the old file on disk instead of overwriting.
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

        $this->assertSame(2, IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->count());
        Storage::disk('public')->assertExists($firstPath);
        $secondPath = IpcAttachment::where('ipc_batch_id', $batch->id)->where('field_label', 'color')->latest('id')->first()->file_path;
        Storage::disk('public')->assertExists($secondPath);
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
        $this->seedPackingFinalizePrereqs($batch);
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload());

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->post("/batches/{$batch->id}/packing-check/photo/color", ['photo' => $photo])->assertForbidden();
    }

    public function test_checklist_matches_the_paper_form_fr_qac_193(): void
    {
        $groups = collect(PackingCheck::checklistGroups())->keyBy('key');

        $this->assertSame(
            ['Bulk', 'Packaging', 'Capping / Sealing', 'Coding Batch & EXP', 'Coding NA', 'Attribute', 'Functional Test'],
            array_values($groups['primary']['fields']),
        );
        $this->assertSame(
            ['Identity', 'Appearance', 'Coding Batch & EXP', 'Coding NA', 'Attribute'],
            array_values($groups['secondary']['fields']),
        );
        $this->assertSame(
            ['Identity', 'Appearance', 'Coding Batch & EXP', 'Coding NA', 'Shipper Label'],
            array_values($groups['tersier']['fields']),
        );

        foreach ($groups as $group) {
            foreach (array_keys($group['fields']) as $field) {
                $this->assertArrayHasKey($field, PackingCheck::SEVERITY_LABELS);
            }
        }
    }

    public function test_new_paper_form_items_are_saved_and_snapshotted_into_the_revision(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload([
            'primary_capping_sealing_status' => PackingCheck::STATUS_NOT_CONFORM,
            'tersier_shipper_label_status' => PackingCheck::STATUS_NA,
        ]))->assertSessionHasNoErrors();

        $packingCheck = $batch->fresh()->packingCheck;
        $this->assertSame(PackingCheck::STATUS_NOT_CONFORM, $packingCheck->primary_capping_sealing_status);
        $this->assertSame(PackingCheck::STATUS_NA, $packingCheck->tersier_shipper_label_status);
        $this->assertSame(PackingCheck::STATUS_CONFORM, $packingCheck->secondary_coding_batch_exp_status);

        $revision = $packingCheck->revisions()->first();
        $this->assertSame(PackingCheck::STATUS_NOT_CONFORM, $revision->primary_capping_sealing_status);
        $this->assertSame(PackingCheck::STATUS_NA, $revision->tersier_shipper_label_status);
    }

    public function test_new_paper_form_items_are_required_even_on_a_draft_save(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();

        $payload = $this->validPayload(['finalize' => false]);
        unset($payload['primary_capping_sealing_status'], $payload['tersier_shipper_label_status']);

        $this->put("/batches/{$batch->id}/packing-check", $payload)
            ->assertSessionHasErrors(['primary_capping_sealing_status', 'tersier_shipper_label_status']);
    }

    public function test_weighing_data_is_required_to_finalize_and_must_be_ada_or_tidak_ada(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['weighing_data' => null]))
            ->assertSessionHasErrors('weighing_data');
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['weighing_data' => 'Mungkin']))
            ->assertSessionHasErrors('weighing_data');
    }

    public function test_weighing_data_is_locked_after_the_first_round(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = $this->makeBatchWithCompletedFillingCheck();
        $this->seedPackingFinalizePrereqs($batch);

        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['finalize' => false, 'weighing_data' => 'Tidak Ada']));
        $this->put("/batches/{$batch->id}/packing-check", $this->validPayload(['weighing_data' => 'Ada']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Tidak Ada', $batch->fresh()->packingCheck->weighing_data);
    }
}
