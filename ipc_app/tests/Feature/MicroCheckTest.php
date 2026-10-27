<?php

namespace Tests\Feature;

use App\Http\Controllers\FinishedCheckController;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MicroCheck;
use App\Models\PackingCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(string $stage = IpcBatch::STAGE_FINISHED, bool $withFinishedSaved = true, string $flow = IpcBatch::FLOW_FULL): IpcBatch
    {
        $product = MasterProduct::firstOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::firstOrCreate(['code' => 'MU 01'], ['category' => 'Packing', 'area' => 'Make Up', 'name' => 'Make Up 01', 'is_active' => true]);
        $creator = User::factory()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-'.IpcBatch::count(),
            'master_line_id' => $line->id,
            'created_by' => $creator->id,
            'current_stage' => $stage,
            'flow_type' => $flow,
        ]);

        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $creator->id, 'decision' => PackingCheck::DECISION_PASSED, 'save_count' => 1, 'completed_at' => now()]);

        if ($withFinishedSaved) {
            FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $creator->id, 'save_count' => 1]);
        }

        return $batch->fresh();
    }

    private function microPayload(array $overrides = []): array
    {
        return [
            'total_plate_count_result' => '<10',
            'total_plate_count_std' => '<100',
            'yeast_mold_result' => '<10',
            'yeast_mold_std' => '<100',
            'pathogen_result' => '(-)',
            'pathogen_std' => '(-)',
            ...$overrides,
        ];
    }

    private function finalizePayload(): array
    {
        return [
            'finalize' => true,
            'quantity_wi' => 500,
            'masterbox' => '4,17',
            'no_pallet_qty' => '1/2016',
            'quantity_sampling_aql' => 50,
            'quantity_sample_aql_cd' => 0,
            'quantity_sample_aql_md' => 1,
            'quantity_sample_aql_mnd' => 2,
            'quantity_special_inspection' => 5,
            'quantity_special_inspection_cd' => 0,
            'quantity_special_inspection_md' => 0,
            'quantity_special_inspection_mnd' => 0,
            'disposition' => FinishedCheck::DISPOSITION_ACCEPTED,
            'remarks' => 'ok',
            'samples' => collect(FinishedCheckSample::PARAMETER_KEYS)->mapWithKeys(fn ($key) => [$key => ['ac' => 1]])->all(),
        ];
    }

    private function seedPhotos(IpcBatch $batch): void
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

    // --- Role + Micro Check input ---

    public function test_microbiologist_role_exists_and_defaults_are_not_privileged(): void
    {
        $user = User::factory()->microbiologist()->create()->fresh();

        $this->assertSame(User::ROLE_MICROBIOLOGIST, $user->role);
        $this->assertFalse($user->isStaff());
        $this->assertFalse($user->isApprover());
        $this->assertTrue($user->isMicrobiologist());
    }

    public function test_microbiologist_can_save_micro_check_and_every_save_is_logged(): void
    {
        $user = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch();

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload())
            ->assertRedirect("/batches/{$batch->id}/micro-check");

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['pathogen_result' => '(+)']))
            ->assertRedirect();

        $micro = $batch->fresh()->microCheck;
        $this->assertSame('(+)', $micro->pathogen_result);
        $this->assertSame($user->id, $micro->user_id);
        $this->assertSame(2, $micro->save_count);
        $this->assertSame(2, $micro->revisions()->count());
        $this->assertSame('(-)', $micro->revisions()->where('revision_no', 1)->value('pathogen_result'));
    }

    public function test_every_micro_field_is_required(): void
    {
        $user = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch();

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['yeast_mold_std' => '']))
            ->assertSessionHasErrors('yeast_mold_std');

        $this->assertNull($batch->fresh()->microCheck);
    }

    public function test_micro_check_is_closed_before_finished_good_is_saved_and_after_it_is_completed(): void
    {
        $user = User::factory()->microbiologist()->create();

        $unsaved = $this->makeBatch(withFinishedSaved: false);
        $this->actingAs($user)->put("/batches/{$unsaved->id}/micro-check", $this->microPayload())->assertForbidden();

        $done = $this->makeBatch();
        $done->finishedCheck->update(['completed_at' => now()]);
        $this->actingAs($user)->put("/batches/{$done->id}/micro-check", $this->microPayload())->assertForbidden();
    }

    public function test_batch_without_finished_stage_has_no_micro_check(): void
    {
        $user = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch(flow: IpcBatch::FLOW_FILLING);

        $this->actingAs($user)->get("/batches/{$batch->id}/micro-check")->assertNotFound();
    }

    public function test_staff_and_approver_cannot_save_micro_check_but_admin_can(): void
    {
        $batch = $this->makeBatch();

        foreach ([User::factory()->create(), User::factory()->approver()->create()] as $user) {
            $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload())->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())->put("/batches/{$batch->id}/micro-check", $this->microPayload())->assertRedirect();
        $this->assertNotNull($batch->fresh()->microCheck);
    }

    public function test_micro_check_queue_lists_open_finished_good_batches_for_microbiologist_only(): void
    {
        $open = $this->makeBatch();
        $done = $this->makeBatch();
        $done->finishedCheck->update(['completed_at' => now()]);

        $this->actingAs(User::factory()->microbiologist()->create())
            ->get('/micro-checks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('batches.data', 1)->where('batches.data.0.id', $open->id));

        $this->actingAs(User::factory()->create())->get('/micro-checks')->assertForbidden();
        $this->actingAs(User::factory()->approver()->create())->get('/micro-checks')->assertForbidden();
    }

    // --- Finished Good is blocked until Micro Check is complete ---

    public function test_finalize_is_blocked_without_micro_check_and_nothing_is_completed(): void
    {
        $staff = User::factory()->create();
        $batch = $this->makeBatch();
        $this->seedPhotos($batch);

        $this->actingAs($staff)->put("/batches/{$batch->id}/finished-check", $this->finalizePayload())
            ->assertSessionHasErrors(['micro_check' => FinishedCheck::MICRO_CHECK_REQUIRED_MESSAGE]);

        $this->assertNull($batch->fresh()->finishedCheck->completed_at);
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);
    }

    public function test_finalize_from_history_is_blocked_without_micro_check(): void
    {
        $staff = User::factory()->create();
        $batch = $this->makeBatch();
        $batch->finishedCheck->revisions()->create(['revision_no' => 1, 'user_id' => $staff->id, 'finalize' => false]);

        $this->actingAs($staff)->post("/batches/{$batch->id}/finished-check/complete")
            ->assertSessionHasErrors(['micro_check' => FinishedCheck::MICRO_CHECK_REQUIRED_MESSAGE]);

        $this->assertNull($batch->fresh()->finishedCheck->completed_at);
    }

    public function test_draft_save_still_works_without_micro_check(): void
    {
        $staff = User::factory()->create();
        $batch = $this->makeBatch();

        $payload = ['finalize' => false, ...$this->finalizePayload()];
        $payload['finalize'] = false;

        $this->actingAs($staff)->put("/batches/{$batch->id}/finished-check", $payload)->assertSessionDoesntHaveErrors();
    }

    public function test_finalize_succeeds_once_micro_check_is_complete_and_then_micro_is_locked(): void
    {
        $staff = User::factory()->create();
        $micro = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch();
        $this->seedPhotos($batch);

        $this->actingAs($micro)->put("/batches/{$batch->id}/micro-check", $this->microPayload())->assertRedirect();

        $this->actingAs($staff)->put("/batches/{$batch->id}/finished-check", $this->finalizePayload())->assertSessionDoesntHaveErrors();

        $this->assertNotNull($batch->fresh()->finishedCheck->completed_at);
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->fresh()->current_stage);

        $this->actingAs($micro)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['pathogen_result' => 'x']))->assertForbidden();
    }

    // --- RBAC strengthening ---

    public function test_microbiologist_cannot_create_batches_or_edit_any_stage(): void
    {
        $micro = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch();

        $this->actingAs($micro)->get('/batches/create')->assertForbidden();
        $this->actingAs($micro)->post('/batches', [])->assertForbidden();
        $this->actingAs($micro)->get("/batches/{$batch->id}/startup-check")->assertForbidden();
        $this->actingAs($micro)->get("/batches/{$batch->id}/filling-check")->assertForbidden();
        $this->actingAs($micro)->get("/batches/{$batch->id}/packing-check")->assertForbidden();
        $this->actingAs($micro)->put("/batches/{$batch->id}/finished-check", ['finalize' => false])->assertForbidden();
        $this->actingAs($micro)->get("/batches/{$batch->id}/print")->assertForbidden();
        $this->actingAs($micro)->get('/approvals')->assertForbidden();
    }

    public function test_microbiologist_can_open_finished_good_read_only_and_only_after_it_was_saved(): void
    {
        $micro = User::factory()->microbiologist()->create();
        $saved = $this->makeBatch();
        $unsaved = $this->makeBatch(withFinishedSaved: false);

        $this->actingAs($micro)->get("/batches/{$saved->id}/finished-check")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isReadOnly', true));
        $this->actingAs($micro)->get("/batches/{$saved->id}")->assertOk();
        $this->actingAs($micro)->get("/batches/{$unsaved->id}")->assertForbidden();
        $this->actingAs($micro)->get("/batches/{$unsaved->id}/finished-check")->assertForbidden();
    }

    public function test_approver_cannot_create_batches_or_open_working_stages(): void
    {
        $approver = User::factory()->approver()->create();
        $batch = $this->makeBatch(IpcBatch::STAGE_FILLING);

        $this->actingAs($approver)->get('/batches/create')->assertForbidden();
        $this->actingAs($approver)->get("/batches/{$batch->id}/filling-check")->assertForbidden();
        $this->actingAs($approver)->get("/batches/{$batch->id}/finished-check")->assertForbidden();
        // A batch still being worked on is not visible to the approver at all.
        $this->actingAs($approver)->get("/batches/{$batch->id}")->assertForbidden();
        $this->actingAs($approver)->get("/batches/{$batch->id}/print/finished/pdf")->assertForbidden();
    }

    public function test_approver_sees_only_batches_from_approval_onwards(): void
    {
        $approver = User::factory()->approver()->create();
        $working = $this->makeBatch(IpcBatch::STAGE_FILLING);
        $approval = $this->makeBatch(IpcBatch::STAGE_APPROVAL);
        $printed = $this->makeBatch(IpcBatch::STAGE_PRINT);

        $this->actingAs($approver)->get("/batches/{$approval->id}")->assertOk();
        $this->actingAs($approver)->get("/batches/{$printed->id}")->assertOk();

        $this->actingAs($approver)->get('/batches')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('batches.data', 2));
        $this->assertTrue(IpcBatch::query()->visibleTo($approver)->whereKey($working->id)->doesntExist());
    }

    public function test_staff_and_admin_still_see_everything_and_can_create_batches(): void
    {
        $this->makeBatch(IpcBatch::STAGE_FILLING);
        $this->makeBatch(IpcBatch::STAGE_APPROVAL);

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/batches')->assertOk()->assertInertia(fn ($page) => $page->has('batches.data', 2));
            $this->actingAs($user)->get('/batches/create')->assertOk();
        }
    }

    public function test_finished_good_pdf_prints_the_micro_check_values(): void
    {
        $batch = $this->makeBatch(IpcBatch::STAGE_PRINT);
        $batch->finishedCheck->update(['completed_at' => now()]);
        $micro = User::factory()->microbiologist()->create(['name' => 'Indah Mikro']);
        MicroCheck::create([...$this->microPayload(['total_plate_count_result' => '<77']), 'ipc_batch_id' => $batch->id, 'user_id' => $micro->id]);

        $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print/finished/preview")
            ->assertOk()
            ->assertSee('&lt;77', false)
            ->assertSee('Indah Mikro');
    }
}
