<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }

    private function makeBatch(string $stage, string $noBatch = 'BATCH-001'): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-'.$noBatch, 'product_name' => 'Sample Product', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU-'.$noBatch, 'name' => 'Make Up', 'is_active' => true]);
        $creator = User::factory()->create();

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => $noBatch,
            'master_line_id' => $line->id,
            'created_by' => $creator->id,
            'current_stage' => $stage,
        ]);
    }

    public function test_stats_count_every_batch_not_just_a_recent_slice(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeBatch(IpcBatch::STAGE_STARTUP, "BATCH-{$i}");
        }

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stats.activeBatches', 25)
            ->where('stats.needsActionCount', 25)
        );
    }

    public function test_needs_action_lists_a_batch_whose_current_stage_check_is_not_completed(): void
    {
        $batch = $this->makeBatch(IpcBatch::STAGE_STARTUP);

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('needsAction.0.no_batch', 'BATCH-001')
            ->where('needsAction.0.reason', 'Startup Check belum diisi')
            ->where('needsAction.0.href', route('startup-check.edit', $batch))
        );
    }

    public function test_needs_action_excludes_a_batch_whose_current_stage_check_is_already_completed(): void
    {
        $batch = $this->makeBatch(IpcBatch::STAGE_FILLING);
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'save_count' => 1, 'completed_at' => now()]);

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertInertia(fn ($page) => $page->where('needsAction', []));
    }

    public function test_only_approvers_see_pending_approval_items_in_needs_action(): void
    {
        $batch = $this->makeBatch(IpcBatch::STAGE_APPROVAL);
        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'completed_at' => now()]);

        $staffResponse = $this->actingAs(User::factory()->create())->get('/dashboard');
        $staffResponse->assertInertia(fn ($page) => $page->where('needsAction', []));

        $approverResponse = $this->actingAs(User::factory()->approver()->create())->get('/dashboard');
        $approverResponse->assertInertia(fn ($page) => $page
            ->where('needsAction.0.no_batch', 'BATCH-001')
            ->where('needsAction.0.reason', 'Menunggu approval: '.IpcApproval::STAGE_LABELS[IpcApproval::STAGE_STARTUP])
        );
    }

    public function test_stage_breakdown_and_pending_approval_count_reflect_real_distribution(): void
    {
        $this->makeBatch(IpcBatch::STAGE_STARTUP, 'B-1');
        $this->makeBatch(IpcBatch::STAGE_APPROVAL, 'B-2');
        $this->makeBatch(IpcBatch::STAGE_APPROVAL, 'B-3');
        $this->makeBatch(IpcBatch::STAGE_COMPLETED, 'B-4');

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('stats.pendingApprovalBatches', 2)
            ->where('stats.activeBatches', 3)
            ->has('stageBreakdown', 7)
            ->where('stageBreakdown.0.stage', IpcBatch::STAGE_STARTUP)
            ->where('stageBreakdown.0.count', 1)
            ->where('stageBreakdown.4.stage', IpcBatch::STAGE_APPROVAL)
            ->where('stageBreakdown.4.count', 2)
        );
    }
}
