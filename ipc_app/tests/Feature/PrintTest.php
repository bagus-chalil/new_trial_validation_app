<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcPrintLog;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchAtPrintStage(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_PRINT,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);

        foreach (IpcApproval::STAGES as $stage) {
            IpcApproval::create([
                'ipc_batch_id' => $batch->id,
                'stage' => $stage,
                'decision' => IpcApproval::DECISION_APPROVED,
                'approver_user_id' => $user->id,
                'approved_at' => now(),
            ]);
        }

        return $batch->fresh();
    }

    private function makeBatchNotYetAtPrintStage(): IpcBatch
    {
        $user = User::factory()->create();
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-002',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
        ]);
    }

    public function test_edit_is_forbidden_before_batch_reaches_print_stage(): void
    {
        $batch = $this->makeBatchNotYetAtPrintStage();

        $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print")->assertForbidden();
    }

    public function test_edit_shows_all_three_stages_with_zero_prints(): void
    {
        $batch = $this->makeBatchAtPrintStage();

        $response = $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('print/index')
            ->where('stages.0.stage', 'startup')
            ->where('stages.0.printCount', 0)
            ->where('stages.1.stage', 'filling_packing')
            ->where('stages.2.stage', 'finished')
        );
    }

    public function test_each_stage_has_its_own_detail_page(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('print/startup')->where('printInfo.stage', 'startup'));

        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling-packing")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('print/filling-packing')->where('printInfo.stage', 'filling_packing'));

        $this->actingAs($user)->get("/batches/{$batch->id}/print/finished")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('print/finished')->where('printInfo.stage', 'finished'));
    }

    public function test_detail_pages_are_forbidden_before_batch_reaches_print_stage(): void
    {
        $batch = $this->makeBatchNotYetAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup")->assertForbidden();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling-packing")->assertForbidden();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/finished")->assertForbidden();
    }

    public function test_pdf_route_streams_a_pdf_per_stage(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        foreach (['startup', 'filling_packing', 'finished'] as $stage) {
            $response = $this->actingAs($user)->get("/batches/{$batch->id}/print/{$stage}/pdf");
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        }
    }

    public function test_pdf_route_is_forbidden_before_batch_reaches_print_stage(): void
    {
        $batch = $this->makeBatchNotYetAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertForbidden();
    }

    public function test_unknown_stage_404s(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/bogus/pdf")->assertNotFound();
    }

    public function test_viewing_the_pdf_logs_a_print_entry(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();

        $this->assertDatabaseHas('ipc_print_logs', [
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'printed_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();
        $this->assertSame(2, IpcPrintLog::query()->where('ipc_batch_id', $batch->id)->where('stage', 'startup')->count());
    }

    public function test_printing_all_three_stages_advances_batch_to_completed(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        foreach (['startup', 'filling_packing', 'finished'] as $stage) {
            $this->actingAs($user)->get("/batches/{$batch->id}/print/{$stage}/pdf")->assertOk();
        }

        $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->fresh()->current_stage);
    }

    public function test_printing_only_two_of_three_stages_does_not_advance_batch(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling_packing/pdf")->assertOk();

        $this->assertSame(IpcBatch::STAGE_PRINT, $batch->fresh()->current_stage);
    }

    public function test_print_overview_and_detail_pages_reflect_print_counts_and_last_printed_by(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();

        $this->actingAs($user)->get("/batches/{$batch->id}/print")
            ->assertInertia(fn ($page) => $page
                ->where('stages.0.printCount', 2)
                ->where('stages.0.lastPrintedBy', $user->name)
            );

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup")
            ->assertInertia(fn ($page) => $page
                ->where('printInfo.printCount', 2)
                ->where('printInfo.lastPrintedBy', $user->name)
            );
    }

    public function test_completed_batch_can_still_view_and_reprint(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        foreach (['startup', 'filling_packing', 'finished'] as $stage) {
            $this->actingAs($user)->get("/batches/{$batch->id}/print/{$stage}/pdf")->assertOk();
        }
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->fresh()->current_stage);

        $this->actingAs($user)->get("/batches/{$batch->id}/print")->assertOk();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();

        $this->assertSame(2, IpcPrintLog::query()->where('ipc_batch_id', $batch->id)->where('stage', 'startup')->count());
    }
}
