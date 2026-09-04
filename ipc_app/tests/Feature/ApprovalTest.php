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

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchAtApprovalStage(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
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

    public function test_edit_is_forbidden_before_finished_check_is_completed(): void
    {
        $user = User::factory()->create();
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_FINISHED,
        ]);

        $this->actingAs($user)->get("/batches/{$batch->id}/approval")->assertForbidden();
    }

    public function test_edit_shows_all_three_stages_ready(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $response = $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/approval");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('approval/index')
            ->where('stages.0.stage', 'startup')
            ->where('stages.0.ready', true)
            ->where('stages.1.stage', 'filling_packing')
            ->where('stages.1.ready', true)
            ->where('stages.2.stage', 'finished')
            ->where('stages.2.ready', true)
        );
    }

    public function test_each_stage_has_its_own_detail_page(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/approval/startup")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('approval/startup')->where('stage.stage', 'startup'));

        $this->actingAs($user)->get("/batches/{$batch->id}/approval/filling-packing")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('approval/filling-packing')->where('stage.stage', 'filling_packing'));

        $this->actingAs($user)->get("/batches/{$batch->id}/approval/finished")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('approval/finished')->where('stage.stage', 'finished'));
    }

    public function test_detail_pages_are_forbidden_before_finished_check_is_completed(): void
    {
        $user = User::factory()->create();
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_FINISHED,
        ]);

        $this->actingAs($user)->get("/batches/{$batch->id}/approval/startup")->assertForbidden();
        $this->actingAs($user)->get("/batches/{$batch->id}/approval/filling-packing")->assertForbidden();
        $this->actingAs($user)->get("/batches/{$batch->id}/approval/finished")->assertForbidden();
    }

    public function test_update_redirects_back_to_the_stage_detail_page(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Approved'])
            ->assertRedirect("/batches/{$batch->id}/approval/startup");

        $this->actingAs($user)
            ->put("/batches/{$batch->id}/approval/filling_packing", ['decision' => 'Approved'])
            ->assertRedirect("/batches/{$batch->id}/approval/filling-packing");

        $this->actingAs($user)
            ->put("/batches/{$batch->id}/approval/finished", ['decision' => 'Approved'])
            ->assertRedirect("/batches/{$batch->id}/approval/finished");
    }

    public function test_print_route_streams_a_pdf_per_stage(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $user = User::factory()->create();

        foreach (['startup', 'filling_packing', 'finished'] as $stage) {
            $response = $this->actingAs($user)->get("/batches/{$batch->id}/approval/{$stage}/print");
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        }
    }

    public function test_print_route_is_forbidden_before_finished_check_is_completed(): void
    {
        $user = User::factory()->create();
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_FINISHED,
        ]);

        $this->actingAs($user)->get("/batches/{$batch->id}/approval/startup/print")->assertForbidden();
    }

    public function test_approving_one_stage_does_not_advance_batch_alone(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Approved', 'remarks' => 'OK'])
            ->assertRedirect();

        $this->assertDatabaseHas('ipc_approvals', [
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'decision' => 'Approved',
            'approver_user_id' => $user->id,
        ]);

        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->fresh()->current_stage);
    }

    public function test_approving_all_three_stages_advances_batch_to_print(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $user = User::factory()->create();

        foreach (IpcApproval::STAGES as $stage) {
            $this->actingAs($user)
                ->put("/batches/{$batch->id}/approval/{$stage}", ['decision' => 'Approved'])
                ->assertRedirect();
        }

        $this->assertSame(IpcBatch::STAGE_PRINT, $batch->fresh()->current_stage);
    }

    public function test_rejecting_one_stage_keeps_batch_at_approval_even_if_others_approved(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $user = User::factory()->create();

        $this->actingAs($user)->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Approved']);
        $this->actingAs($user)->put("/batches/{$batch->id}/approval/filling_packing", ['decision' => 'Rejected', 'remarks' => 'Cacat produksi']);
        $this->actingAs($user)->put("/batches/{$batch->id}/approval/finished", ['decision' => 'Approved']);

        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->fresh()->current_stage);
    }

    public function test_invalid_decision_is_rejected(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->create())
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Maybe'])
            ->assertSessionHasErrors('decision');
    }

    public function test_remarks_is_required_when_rejected(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->create())
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Rejected'])
            ->assertSessionHasErrors('remarks');
    }

    public function test_resubmitting_a_decision_updates_the_existing_row_not_a_duplicate(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        $user = User::factory()->create();

        $this->actingAs($user)->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Rejected', 'remarks' => 'Awal salah']);
        $this->actingAs($user)->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Approved']);

        $this->assertDatabaseCount('ipc_approvals', 1);
        $this->assertDatabaseHas('ipc_approvals', [
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'decision' => 'Approved',
        ]);
    }

    public function test_approving_a_stage_that_is_not_ready_is_forbidden(): void
    {
        $batch = $this->makeBatchAtApprovalStage();
        // Simulate the (currently impossible in the real flow) case where Filling & Packing
        // isn't actually ready — proves stageReady() is really enforced server-side, not just
        // trusted from the always-gated real workflow.
        $batch->packingCheck->update(['completed_at' => null]);

        $this->actingAs(User::factory()->create())
            ->put("/batches/{$batch->id}/approval/filling_packing", ['decision' => 'Approved'])
            ->assertForbidden();
    }

    public function test_unknown_stage_404s(): void
    {
        $batch = $this->makeBatchAtApprovalStage();

        $this->actingAs(User::factory()->create())
            ->put("/batches/{$batch->id}/approval/bogus-stage", ['decision' => 'Approved'])
            ->assertNotFound();
    }
}
