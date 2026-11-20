<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two-level approval (2026-11-21): Level 1 = QC Verifikator (`approver`), Level 2 = QC Team Leader /
 * Part Leader / Supervisor (`approver_l2`). A stage is approved only when both levels Approved.
 */
class TwoLevelApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchAtApprovalStage(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-L2',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);

        return $batch->fresh();
    }

    private function decide(User $user, IpcBatch $batch, string $stage, array $payload)
    {
        return $this->actingAs($user)->put("/batches/{$batch->id}/approval/{$stage}", $payload);
    }

    public function test_level_2_cannot_decide_before_level_1_approved(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l2 = User::factory()->approverL2()->create();

        $this->decide($l2, $batch, 'filling', ['decision' => 'Approved', 'level' => 2])
            ->assertSessionHasErrors('decision');

        $this->assertDatabaseMissing('ipc_approvals', ['ipc_batch_id' => $batch->id, 'stage' => 'filling']);
    }

    public function test_level_2_decision_is_stored_in_its_own_columns_and_history(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        $this->decide($l1, $batch, 'filling', ['decision' => 'Approved'])->assertRedirect();
        $this->decide($l2, $batch, 'filling', ['decision' => 'Approved', 'remarks' => 'OK', 'level' => 2])->assertRedirect();

        $approval = IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', 'filling')->firstOrFail();
        $this->assertSame('Approved', $approval->decision);
        $this->assertSame($l1->id, $approval->approver_user_id);
        $this->assertSame('Approved', $approval->l2_decision);
        $this->assertSame($l2->id, $approval->l2_approver_user_id);
        $this->assertSame('OK', $approval->l2_remarks);
        $this->assertNotNull($approval->l2_decided_at);
        $this->assertTrue($approval->isFullyApproved());

        $this->assertDatabaseHas('ipc_approval_revisions', ['ipc_approval_id' => $approval->id, 'level' => 1, 'approver_user_id' => $l1->id]);
        $this->assertDatabaseHas('ipc_approval_revisions', ['ipc_approval_id' => $approval->id, 'level' => 2, 'approver_user_id' => $l2->id]);
    }

    public function test_each_level_is_limited_to_its_own_role(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        // The L2 role can open the approval pages but cannot give a Level 1 decision.
        $this->actingAs($l2)->get("/batches/{$batch->id}/approval")->assertOk();
        $this->decide($l2, $batch, 'filling', ['decision' => 'Approved'])->assertForbidden();

        // The L1 role cannot give a Level 2 decision, even after it approved Level 1 itself.
        $this->decide($l1, $batch, 'filling', ['decision' => 'Approved'])->assertRedirect();
        $this->decide($l1, $batch, 'filling', ['decision' => 'Approved', 'level' => 2])->assertForbidden();

        $this->assertNull(IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', 'filling')->value('l2_decision'));
    }

    public function test_the_same_person_cannot_give_both_levels_on_one_stage(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $admin = User::factory()->admin()->create();

        $this->decide($admin, $batch, 'filling', ['decision' => 'Approved'])->assertRedirect();
        $this->decide($admin, $batch, 'filling', ['decision' => 'Approved', 'level' => 2])->assertSessionHasErrors('decision');

        $this->assertNull(IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', 'filling')->value('l2_decision'));

        // A different person can.
        $this->decide(User::factory()->approverL2()->create(), $batch, 'filling', ['decision' => 'Approved', 'level' => 2])->assertRedirect();
        $this->assertSame('Approved', IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', 'filling')->value('l2_decision'));
    }

    public function test_level_2_cannot_ask_for_a_revision(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $this->decide(User::factory()->approver()->create(), $batch, 'filling', ['decision' => 'Approved']);

        $this->decide(User::factory()->approverL2()->create(), $batch, 'filling', ['decision' => 'Revision', 'remarks' => 'Cek', 'level' => 2])
            ->assertSessionHasErrors('decision');

        $this->assertNull(IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', 'filling')->value('l2_decision'));
    }

    public function test_level_2_rejection_needs_a_reason_and_keeps_the_batch_at_approval(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        foreach (IpcApproval::STAGES as $stage) {
            $this->decide($l1, $batch, $stage, ['decision' => 'Approved'])->assertRedirect();
        }

        $this->decide($l2, $batch, 'filling', ['decision' => 'Rejected', 'level' => 2])->assertSessionHasErrors('remarks');

        $this->decide($l2, $batch, 'filling', ['decision' => 'Rejected', 'remarks' => 'Berat tidak sesuai', 'level' => 2])->assertRedirect();

        $approval = IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', 'filling')->firstOrFail();
        $this->assertSame('Approved', $approval->decision, 'Level 1 decision is kept');
        $this->assertSame('Rejected', $approval->l2_decision);
        $this->assertFalse($approval->isFullyApproved());
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->fresh()->current_stage);
    }

    public function test_after_a_level_2_rejection_only_level_1_can_decide_again_and_that_clears_level_2(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        $this->decide($l1, $batch, 'startup', ['decision' => 'Approved']);
        $this->decide($l2, $batch, 'startup', ['decision' => 'Rejected', 'remarks' => 'Foto kurang jelas', 'level' => 2]);

        // The queue sends it back to Level 1.
        $this->actingAs($l1)->get('/approvals')->assertInertia(fn ($page) => $page
            ->where('queue.data.0.pendingStages.0', 'Startup (L1)'));

        $this->decide($l1, $batch, 'startup', ['decision' => 'Approved', 'remarks' => 'Sudah dicek ulang'])->assertRedirect();

        $approval = IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', 'startup')->firstOrFail();
        $this->assertNull($approval->l2_decision);
        $this->assertNull($approval->l2_approver_user_id);
        $this->assertNull($approval->l2_remarks);
        // The refused Level 2 decision stays in the history.
        $this->assertDatabaseHas('ipc_approval_revisions', ['ipc_approval_id' => $approval->id, 'level' => 2, 'decision' => 'Rejected']);
    }

    public function test_deciding_level_1_again_clears_an_earlier_level_2_approval(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        $this->decide($l1, $batch, 'startup', ['decision' => 'Approved']);
        $this->decide($l2, $batch, 'startup', ['decision' => 'Approved', 'level' => 2]);
        $this->assertTrue(IpcApproval::where('stage', 'startup')->firstOrFail()->isFullyApproved());

        $this->decide($l1, $batch, 'startup', ['decision' => 'Rejected', 'remarks' => 'Salah input']);

        $approval = IpcApproval::where('stage', 'startup')->firstOrFail();
        $this->assertSame('Rejected', $approval->decision);
        $this->assertNull($approval->l2_decision);
        $this->assertFalse($approval->isFullyApproved());
    }

    public function test_combined_form_level_2_decision_is_written_to_every_part(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        $this->decide($l1, $batch, 'startup', ['decision' => 'Approved']);
        $this->decide($l1, $batch, 'filling_packing_fg', ['decision' => 'Approved']);
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->fresh()->current_stage);

        $this->decide($l2, $batch, 'startup', ['decision' => 'Approved', 'level' => 2]);
        $this->decide($l2, $batch, 'filling_packing_fg', ['decision' => 'Approved', 'level' => 2])->assertRedirect();

        foreach (['filling', 'packing', 'finished'] as $stage) {
            $this->assertSame('Approved', IpcApproval::where('ipc_batch_id', $batch->id)->where('stage', $stage)->value('l2_decision'));
        }
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->fresh()->current_stage);
    }

    public function test_queue_labels_the_level_that_decides_next_and_keeps_level_2_pending_batches_listed(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        foreach (IpcApproval::STAGES as $stage) {
            $this->decide($l1, $batch, $stage, ['decision' => 'Approved']);
        }

        // Every stage is Approved by Level 1 only: still in the queue, now waiting on Level 2.
        $this->actingAs($l2)->get('/approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('queue.data', 1)
                ->where('queue.data.0.pendingStages', ['Startup (L2)', 'Filling (L2)', 'Packing (L2)', 'Finished (L2)']));
    }

    public function test_batch_leaves_the_queue_once_both_levels_approved_everything(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create();

        foreach (IpcApproval::STAGES as $stage) {
            $this->decide($l1, $batch, $stage, ['decision' => 'Approved']);
            $this->decide($l2, $batch, $stage, ['decision' => 'Approved', 'level' => 2]);
        }

        $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->fresh()->current_stage);
        $this->actingAs($l2)->get('/approvals')->assertInertia(fn ($page) => $page->has('queue.data', 0));
    }

    public function test_detail_page_exposes_level_2_data_and_the_shared_role_flags(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $l1 = User::factory()->approver()->create();
        $l2 = User::factory()->approverL2()->create(['name' => 'Team Leader QC']);

        $this->decide($l1, $batch, 'filling', ['decision' => 'Approved']);
        $this->decide($l2, $batch, 'filling', ['decision' => 'Approved', 'level' => 2]);

        $this->actingAs($l2)->get("/batches/{$batch->id}/approval/filling")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canApproveL1', false)
                ->where('canApproveL2', true)
                ->where('stage.approval.l2_decision', 'Approved')
                ->where('stage.approval.l2_approver.name', 'Team Leader QC'));

        $this->actingAs($l1)->get("/batches/{$batch->id}/approval/filling")
            ->assertInertia(fn ($page) => $page->where('canApproveL1', true)->where('canApproveL2', false));
    }

    public function test_verification_page_shows_waiting_for_level_2_until_it_approves(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $this->decide(User::factory()->approver()->create(), $batch, 'finished', ['decision' => 'Approved']);

        $this->get("/verify/{$batch->id}/finished")
            ->assertOk()
            ->assertSee('Menunggu Approval Level 2')
            ->assertDontSee('Dokumen Terverifikasi');

        $this->decide(User::factory()->approverL2()->create(), $batch, 'finished', ['decision' => 'Approved', 'level' => 2]);

        $this->get("/verify/{$batch->id}/finished")
            ->assertOk()
            ->assertSee('Dokumen Terverifikasi');
    }
}
