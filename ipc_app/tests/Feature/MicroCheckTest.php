<?php

namespace Tests\Feature;

use App\Models\FinishedCheck;
use App\Models\IpcApproval;
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

    private function makeBatch(
        string $stage = IpcBatch::STAGE_FINISHED,
        bool $withFinishedSaved = true,
        string $flow = IpcBatch::FLOW_FULL,
        bool $finishedCompleted = false,
    ): IpcBatch {
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
            FinishedCheck::create([
                'ipc_batch_id' => $batch->id,
                'user_id' => $creator->id,
                'save_count' => 1,
                'completed_at' => $finishedCompleted ? now() : null,
            ]);
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
        $batch = $this->makeBatch(finishedCompleted: true);

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

    public function test_selesaikan_requires_every_micro_field_and_saves_nothing_when_one_is_missing(): void
    {
        $user = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch(finishedCompleted: true);

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['yeast_mold_std' => '', 'finalize' => true]))
            ->assertSessionHasErrors('yeast_mold_std');

        $this->assertNull($batch->fresh()->microCheck);
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);
    }

    public function test_simpan_progress_accepts_partial_values_but_not_a_blank_form(): void
    {
        $user = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch(finishedCompleted: true);

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload([
            'yeast_mold_result' => '', 'pathogen_result' => '', 'finalize' => false,
        ]))->assertSessionDoesntHaveErrors();

        $micro = $batch->fresh()->microCheck;
        $this->assertNull($micro->completed_at);
        $this->assertFalse($micro->isComplete());
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload([
            'total_plate_count_result' => '', 'yeast_mold_result' => '', 'pathogen_result' => '', 'finalize' => false,
        ]))->assertSessionHasErrors('progress');
    }

    public function test_selesaikan_locks_micro_check_and_sends_the_batch_to_approval(): void
    {
        $user = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch(finishedCompleted: true);

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['finalize' => false]))->assertRedirect();
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['finalize' => true]))
            ->assertRedirect('/micro-checks');

        $micro = $batch->fresh()->microCheck;
        $this->assertNotNull($micro->completed_at);
        $this->assertTrue($micro->revisions()->where('revision_no', 2)->first()->finalize);
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->fresh()->current_stage);

        $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['pathogen_result' => 'x']))->assertForbidden();
    }

    public function test_micro_check_is_closed_until_finished_good_is_finalized(): void
    {
        $user = User::factory()->microbiologist()->create();

        $unsaved = $this->makeBatch(withFinishedSaved: false);
        $this->actingAs($user)->put("/batches/{$unsaved->id}/micro-check", $this->microPayload())->assertForbidden();

        $draft = $this->makeBatch();
        $this->actingAs($user)->put("/batches/{$draft->id}/micro-check", $this->microPayload())->assertForbidden();
        $this->assertNull($draft->fresh()->microCheck);
    }

    public function test_batch_without_finished_stage_has_no_micro_check(): void
    {
        $user = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch(flow: IpcBatch::FLOW_FILLING);

        $this->actingAs($user)->get("/batches/{$batch->id}/micro-check")->assertNotFound();
    }

    public function test_staff_and_approver_cannot_save_micro_check_but_admin_can(): void
    {
        $batch = $this->makeBatch(finishedCompleted: true);

        foreach ([User::factory()->create(), User::factory()->approver()->create()] as $user) {
            $this->actingAs($user)->put("/batches/{$batch->id}/micro-check", $this->microPayload())->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())->put("/batches/{$batch->id}/micro-check", $this->microPayload())->assertRedirect();
        $this->assertNotNull($batch->fresh()->microCheck);
    }

    public function test_micro_check_queue_lists_finalized_finished_good_without_finalized_micro_check(): void
    {
        $waiting = $this->makeBatch(finishedCompleted: true);
        $this->makeBatch(); // Finished Good still a draft — not in the queue yet.
        $sent = $this->makeBatch(finishedCompleted: true);
        MicroCheck::create([...$this->microPayload(), 'ipc_batch_id' => $sent->id, 'user_id' => User::factory()->microbiologist()->create()->id, 'completed_at' => now()]);

        $this->actingAs(User::factory()->microbiologist()->create())
            ->get('/micro-checks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('batches.data', 1)->where('batches.data.0.id', $waiting->id));

        $this->actingAs(User::factory()->create())->get('/micro-checks')->assertForbidden();
        $this->actingAs(User::factory()->approver()->create())->get('/micro-checks')->assertForbidden();
    }

    // --- Finished Good is Staff's responsibility and is not blocked by Micro Check ---

    public function test_finished_good_can_be_finalized_without_micro_check_and_stays_at_the_finished_stage(): void
    {
        $staff = User::factory()->create();
        $batch = $this->makeBatch();
        $batch->finishedCheck->revisions()->create(['revision_no' => 1, 'user_id' => $staff->id, 'finalize' => false]);

        $this->actingAs($staff)->post("/batches/{$batch->id}/finished-check/complete")->assertSessionDoesntHaveErrors();

        $this->assertNotNull($batch->fresh()->finishedCheck->completed_at);
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);
        $this->assertFalse(IpcApproval::stageReady($batch->fresh(), IpcApproval::STAGE_FINISHED));
    }

    public function test_finished_stage_is_approvable_once_micro_check_is_finalized(): void
    {
        $micro = User::factory()->microbiologist()->create();
        $batch = $this->makeBatch(finishedCompleted: true);

        $this->actingAs($micro)->put("/batches/{$batch->id}/micro-check", $this->microPayload(['finalize' => true]))->assertRedirect();

        $this->assertTrue(IpcApproval::stageReady($batch->fresh(), IpcApproval::STAGE_FINISHED));
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

    public function test_batch_detail_shows_that_it_is_waiting_for_micro_check_once_finished_good_is_done(): void
    {
        $staff = User::factory()->create();
        $waiting = $this->makeBatch(finishedCompleted: true);
        $draft = $this->makeBatch();

        $this->actingAs($staff)->get("/batches/{$waiting->id}")
            ->assertInertia(fn ($page) => $page->where('microCheckWaiting.hasProgress', false)->where('microCheckWaiting.href', null));
        $this->actingAs($staff)->get("/batches/{$draft->id}")
            ->assertInertia(fn ($page) => $page->where('microCheckWaiting', null));

        $this->actingAs(User::factory()->microbiologist()->create())->get("/batches/{$waiting->id}")
            ->assertInertia(fn ($page) => $page->where('microCheckWaiting.href', route('micro-checks.edit', $waiting)));
    }

    public function test_detail_places_micro_check_between_finished_good_and_locked_approval(): void
    {
        $staff = User::factory()->create();
        $batch = $this->makeBatch(finishedCompleted: true);
        $this->actingAs($staff)->get("/batches/{$batch->id}")
            ->assertInertia(fn ($page) => $page
                ->where('stages.3.key', 'finished')
                ->where('stages.3.status', 'done')
                ->where('stages.4.key', 'micro')
                ->where('stages.4.status', 'active')
                ->where('stages.4.href', null)
                ->where('stages.5.key', 'approval')
                ->where('stages.5.status', 'locked')
                ->where('stages.5.href', null));

        $microbiologist = User::factory()->microbiologist()->create();
        $this->actingAs($microbiologist)->get("/batches/{$batch->id}")
            ->assertInertia(fn ($page) => $page->where('stages.4.href', route('micro-checks.edit', $batch)));

        $this->put("/batches/{$batch->id}/micro-check", $this->microPayload())->assertRedirect();
        $this->get("/batches/{$batch->id}")
            ->assertInertia(fn ($page) => $page
                ->where('stages.4.status', 'active')
                ->where('stages.5.status', 'locked'));
    }

    public function test_detail_marks_micro_done_only_after_it_is_completed(): void
    {
        $batch = $this->makeBatch(finishedCompleted: true);
        $micro = User::factory()->microbiologist()->create();
        MicroCheck::create([...$this->microPayload(), 'ipc_batch_id' => $batch->id, 'user_id' => $micro->id, 'completed_at' => now()]);
        $batch->update(['current_stage' => IpcBatch::STAGE_APPROVAL]);
        $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}")
            ->assertInertia(fn ($page) => $page
                ->where('stages.4.key', 'micro')
                ->where('stages.4.status', 'done')
                ->where('stages.5.key', 'approval')
                ->where('stages.5.status', 'active'));
    }
}
