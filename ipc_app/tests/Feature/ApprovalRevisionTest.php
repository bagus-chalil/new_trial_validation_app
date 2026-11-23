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
use App\Models\StartupInspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Minta Revisi": an approver sends a report back to QC instead of approving/rejecting it. */
class ApprovalRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchAtApprovalStage(string $stage = IpcBatch::STAGE_APPROVAL): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->approver()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-REV',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => $stage,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);
        StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);

        return $batch->fresh();
    }

    public function test_revision_reopens_only_the_chosen_stage_and_sends_batch_back(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $approver = User::factory()->approver()->create();

        $this->actingAs($approver)
            ->put("/batches/{$batch->id}/approval/filling_packing_fg", [
                'decision' => 'Revision',
                'remarks' => 'Berat sampel 3 salah ketik.',
                'revise_stages' => ['filling'],
            ])
            ->assertRedirect(route('approvals.index'));

        $batch->refresh();
        $this->assertNull($batch->fillingCheck->completed_at);
        $this->assertNotNull($batch->packingCheck->completed_at);
        $this->assertNotNull($batch->finishedCheck->completed_at);
        $this->assertSame(IpcBatch::STAGE_FILLING, $batch->current_stage);

        // One document, one decision: all parts of the combined form carry the Revision decision.
        $this->assertSame(3, $batch->approvals()->where('decision', IpcApproval::DECISION_REVISION)->count());
        $this->assertSame('Berat sampel 3 salah ketik.', $batch->approvals()->where('stage', 'filling')->value('remarks'));
        $this->assertTrue($batch->hasOpenRevision());
        $this->assertSame(['filling'], $batch->openRevisionStages());
    }

    public function test_reopened_stage_can_be_edited_and_batch_leaves_the_approval_queue(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $approver = User::factory()->approver()->create();

        $this->actingAs($approver)->put("/batches/{$batch->id}/approval/filling_packing_fg", [
            'decision' => 'Revision',
            'remarks' => 'Perbaiki.',
            'revise_stages' => ['filling'],
        ]);

        $this->assertFalse(IpcBatch::query()->pendingApproval()->whereKey($batch->id)->exists());
        $this->actingAs($approver)->get("/batches/{$batch->id}/approval")->assertForbidden();
    }

    public function test_revision_requires_a_note(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/filling_packing_fg", ['decision' => 'Revision', 'revise_stages' => ['filling']])
            ->assertSessionHasErrors('remarks');

        $this->assertNotNull($batch->fresh()->fillingCheck->completed_at);
    }

    public function test_combined_report_revision_requires_at_least_one_stage_from_that_report(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $approver = User::factory()->approver()->create();

        $this->actingAs($approver)
            ->put("/batches/{$batch->id}/approval/filling_packing_fg", ['decision' => 'Revision', 'remarks' => 'x'])
            ->assertSessionHasErrors('revise_stages');

        $this->actingAs($approver)
            ->put("/batches/{$batch->id}/approval/filling_packing_fg", ['decision' => 'Revision', 'remarks' => 'x', 'revise_stages' => ['startup']])
            ->assertSessionHasErrors('revise_stages.0');
    }

    public function test_single_stage_report_revision_reopens_that_stage_including_start_inspection(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Revision', 'remarks' => 'Foto IM buram.'])
            ->assertRedirect(route('approvals.index'));

        $batch->refresh();
        $this->assertNull($batch->startupCheck->completed_at);
        $this->assertNull($batch->startupInspection->completed_at);
        $this->assertSame(IpcBatch::STAGE_STARTUP, $batch->current_stage);
        $this->assertSame(1, $batch->approvals()->where('decision', IpcApproval::DECISION_REVISION)->count());
    }

    public function test_single_stage_revision_works_when_the_form_sends_an_empty_stage_list(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        // The approval form always includes `revise_stages: []` for single-stage reports.
        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Revision', 'remarks' => 'Foto IM buram.', 'revise_stages' => []])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('approvals.index'));

        $this->assertNull($batch->fresh()->startupCheck->completed_at);
    }

    public function test_revision_is_refused_once_the_batch_is_past_approval(): void
    {
        $batch = $this->makeBatchAtApprovalStage(IpcBatch::STAGE_PRINT);

        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/filling_packing_fg", ['decision' => 'Revision', 'remarks' => 'x', 'revise_stages' => ['filling']])
            ->assertSessionHasErrors('decision');

        $this->assertNotNull($batch->fresh()->fillingCheck->completed_at);
    }

    public function test_finalizing_the_last_reopened_stage_returns_the_batch_to_approval_and_clears_the_revision(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->approver()->create())->put("/batches/{$batch->id}/approval/filling_packing_fg", [
            'decision' => 'Revision',
            'remarks' => 'Perbaiki.',
            'revise_stages' => ['filling', 'finished'],
        ]);

        $batch->refresh();
        $this->assertSame(['filling', 'finished'], $batch->openRevisionStages());

        // QC finalizes Filling: Packing was never reopened (already complete), Finished still is.
        $batch->fillingCheck->update(['completed_at' => now()]);
        $batch->advanceFrom(IpcBatch::STAGE_FILLING);
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);
        $this->assertTrue($batch->fresh()->hasOpenRevision());

        // Then Finished: batch is back at Approval and the revision request is closed.
        $batch->finishedCheck->update(['completed_at' => now()]);
        $batch->fresh()->advanceFrom(IpcBatch::STAGE_FINISHED);

        $batch->refresh();
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->current_stage);
        $this->assertFalse($batch->hasOpenRevision());
        $this->assertSame([], $batch->openRevisionStages());
        $this->assertTrue(IpcBatch::query()->pendingApproval()->whereKey($batch->id)->exists());
    }

    public function test_every_decision_is_kept_in_the_approval_history(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->approver()->create())->put("/batches/{$batch->id}/approval/startup", [
            'decision' => 'Revision',
            'remarks' => 'Foto IM buram.',
        ]);

        $approval = $batch->approvals()->where('stage', 'startup')->first();
        $this->assertSame(1, $approval->revisions()->count());
        $this->assertSame('Revision', $approval->revisions()->first()->decision);
    }

    public function test_stage_page_shares_the_revision_note_with_qc(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->approver()->create())->put("/batches/{$batch->id}/approval/filling_packing_fg", [
            'decision' => 'Revision',
            'remarks' => 'Berat sampel 3 salah ketik.',
            'revise_stages' => ['filling'],
        ]);

        $this->actingAs(User::factory()->create())
            ->get("/batches/{$batch->id}/filling-check")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('revisionRequest.remarks', 'Berat sampel 3 salah ketik.')
                ->where('revisionRequest.stages', ['Filling']));
    }
}
