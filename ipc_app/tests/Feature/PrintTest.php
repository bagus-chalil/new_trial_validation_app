<?php

namespace Tests\Feature;

use App\Http\Controllers\FillingCheckController;
use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcPrintLog;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\StartupInspection;
use App\Models\StartupInspectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchAtPrintStage(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
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
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
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

    public function test_edit_shows_startup_and_fillpack_cards_for_the_full_flow(): void
    {
        $batch = $this->makeBatchAtPrintStage();

        $response = $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('print/index')
            ->has('stages', 2)
            ->where('stages.0.stage', 'startup')
            ->where('stages.0.printCount', 0)
            ->where('stages.1.stage', 'filling_packing_fg')
        );
    }

    public function test_each_stage_has_its_own_detail_page(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('print/startup')->where('printInfo.stage', 'startup'));

        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('print/filling-packing')->where('side', 'filling')->where('printInfo.stage', 'filling'));

        $this->actingAs($user)->get("/batches/{$batch->id}/print/packing")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('print/filling-packing')->where('side', 'packing')->where('printInfo.stage', 'packing'));

        $this->actingAs($user)->get("/batches/{$batch->id}/print/finished")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('print/finished')->where('printInfo.stage', 'finished'));
    }

    public function test_detail_pages_are_forbidden_before_batch_reaches_print_stage(): void
    {
        $batch = $this->makeBatchNotYetAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup")->assertForbidden();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling")->assertForbidden();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/packing")->assertForbidden();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/finished")->assertForbidden();
    }

    public function test_pdf_route_streams_a_pdf_per_stage(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        foreach (['startup', 'filling_packing_fg'] as $stage) {
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

    public function test_preview_route_streams_a_pdf_without_logging_a_print_entry(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        foreach (['startup', 'filling_packing_fg'] as $stage) {
            $response = $this->actingAs($user)->get("/batches/{$batch->id}/print/{$stage}/preview");
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        }

        $this->assertSame(0, IpcPrintLog::query()->where('ipc_batch_id', $batch->id)->count());
    }

    public function test_startup_pdf_sign_off_boxes_show_operator_line_leader_and_approver_names(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $batch->startupCheck->update(['operator_name' => 'Siti Operator', 'line_leader_name' => 'Budi Leader']);

        // The QC Coordinator box only renders once a Start Inspection exists.
        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'completed_at' => now()]);
        StartupInspectionItem::create(['startup_inspection_id' => $inspection->id, 'parameter_key' => 'bulk_odor', 'status' => 'Conform']);

        $approver = User::factory()->create(['name' => 'Rina Coordinator']);
        IpcApproval::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', IpcApproval::STAGE_STARTUP)
            ->update(['approver_user_id' => $approver->id]);

        $response = $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print/startup/preview");

        $response->assertOk();
        $response->assertSee('Siti Operator');
        $response->assertSee('Budi Leader');
        $response->assertSee('Rina Coordinator');
    }

    public function test_startup_pdf_falls_back_to_filling_approver_when_startup_has_no_approval(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'completed_at' => now()]);
        StartupInspectionItem::create(['startup_inspection_id' => $inspection->id, 'parameter_key' => 'bulk_odor', 'status' => 'Conform']);

        IpcApproval::query()->where('ipc_batch_id', $batch->id)->where('stage', IpcApproval::STAGE_STARTUP)->delete();
        $coordinator = User::factory()->create(['name' => 'Dewi Coordinator']);
        IpcApproval::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', IpcApproval::STAGE_FILLING)
            ->update(['approver_user_id' => $coordinator->id]);

        $response = $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print/startup/preview");

        $response->assertOk();
        $response->assertSee('Dewi Coordinator');
        $response->assertSee('qr-code', false);
        $response->assertDontSee('Belum disetujui');

        $this->get("/verify/{$batch->id}/startup")->assertOk()->assertSee('Dewi Coordinator');
    }

    public function test_pdfs_print_the_approval_remarks_and_the_approver_even_when_rejected(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $batch->created_by, 'completed_at' => now()]);
        StartupInspectionItem::create(['startup_inspection_id' => $inspection->id, 'parameter_key' => 'bulk_odor', 'status' => 'Conform']);
        $coordinator = User::factory()->create(['name' => 'Rudi Coordinator']);
        IpcApproval::query()->where('ipc_batch_id', $batch->id)->update([
            'approver_user_id' => $coordinator->id,
            'decision' => IpcApproval::DECISION_REJECTED,
            'remarks' => 'Coding batch kurang jelas',
        ]);
        $viewer = User::factory()->create();

        foreach (['startup', 'filling_packing_fg'] as $stage) {
            $this->actingAs($viewer)->get("/batches/{$batch->id}/print/{$stage}/preview")
                ->assertOk()
                ->assertSee('Rudi Coordinator')
                ->assertSee('Catatan Approval')
                ->assertSee('Coding batch kurang jelas');
        }
    }

    public function test_filling_and_packing_pdfs_are_issued_by_their_own_latest_round_user(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $fillingUser = User::factory()->create(['name' => 'Ani Filling']);
        $packingUser = User::factory()->create(['name' => 'Budi Packing']);

        $batch->fillingCheck->revisions()->create(['revision_no' => 1, 'finalize' => true, 'user_id' => $fillingUser->id]);
        $batch->packingCheck->revisions()->create(['revision_no' => 1, 'finalize' => true, 'user_id' => $packingUser->id]);
        $viewer = User::factory()->create();

        $fillingHtml = $this->actingAs($viewer)->get("/batches/{$batch->id}/print/filling/preview")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Issued By \(QC Filling\)<\/span>\s*<strong>Ani Filling<\/strong>/', $fillingHtml);
        $this->assertStringNotContainsString('Budi Packing', $fillingHtml);

        $packingHtml = $this->actingAs($viewer)->get("/batches/{$batch->id}/print/packing/preview")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Issued By \(QC Packing\)<\/span>\s*<strong>Budi Packing<\/strong>/', $packingHtml);
        $this->assertStringNotContainsString('Ani Filling', $packingHtml);
    }

    // --- Combined Filling + Packing document (FR.QAC.193.00) — additive, flow Lengkap (full) only ---

    private function makeFillingFlowBatchAtPrintStage(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-FILLING-ONLY', 'product_name' => 'Product Filling Only', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 02', 'name' => 'Make Up 02', 'is_active' => true]);
        $user = User::factory()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-FILLING-ONLY',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_PRINT,
            'flow_type' => IpcBatch::FLOW_FILLING,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        IpcApproval::create([
            'ipc_batch_id' => $batch->id,
            'stage' => IpcApproval::STAGE_FILLING,
            'decision' => IpcApproval::DECISION_APPROVED,
            'approver_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        return $batch->fresh();
    }

    public function test_combined_filling_packing_document_is_only_offered_for_the_full_flow(): void
    {
        $fullFlowBatch = $this->makeBatchAtPrintStage();
        $fillingOnlyBatch = $this->makeFillingFlowBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$fullFlowBatch->id}/print")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stages.1.stage', 'filling_packing_fg'));

        $this->actingAs($user)->get("/batches/{$fillingOnlyBatch->id}/print")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('stages', 2));
    }

    public function test_combined_filling_packing_pdf_404s_for_a_non_full_flow_batch(): void
    {
        $batch = $this->makeFillingFlowBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling_packing_fg/preview")->assertNotFound();
    }

    public function test_combined_filling_packing_pdf_renders_both_sections_with_their_own_sign_off(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $fillingUser = User::factory()->create(['name' => 'Ani Filling']);
        $packingUser = User::factory()->create(['name' => 'Budi Packing']);
        $batch->fillingCheck->revisions()->create(['revision_no' => 1, 'finalize' => true, 'user_id' => $fillingUser->id]);
        $batch->packingCheck->revisions()->create(['revision_no' => 1, 'finalize' => true, 'user_id' => $packingUser->id]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/batches/{$batch->id}/print/filling_packing_fg/preview");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $html = $response->getContent();
        // Portrait Filling+Packing page followed by a landscape Finished Good page in one PDF.
        $this->assertStringStartsWith('%PDF-FAKE-MIXED', $html);
        $this->assertStringContainsString('size: A4 landscape', $html);
        $this->assertStringContainsString('Laporan Pemeriksaan Finished Good', $html);
        $this->assertStringContainsString('FR.QAC.193.00', $html);
        $this->assertStringContainsString('Filling Inspection', $html);
        $this->assertStringContainsString('Packing Inspection', $html);
        $this->assertMatchesRegularExpression('/Issued By \(QC Filling\)<\/span>\s*<strong>Ani Filling<\/strong>/', $html);
        $this->assertMatchesRegularExpression('/Issued By \(QC Packing\)<\/span>\s*<strong>Budi Packing<\/strong>/', $html);
    }

    public function test_combined_filling_packing_pdf_is_logged_as_its_own_print_stage(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling_packing_fg/pdf")->assertOk();

        $this->assertDatabaseHas('ipc_print_logs', [
            'ipc_batch_id' => $batch->id,
            'stage' => 'filling_packing_fg',
            'printed_by_user_id' => $user->id,
        ]);
    }

    public function test_printing_startup_and_fillpack_completes_a_full_flow_batch(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling_packing_fg/pdf")->assertOk();
        $this->assertSame(IpcBatch::STAGE_PRINT, $batch->fresh()->current_stage);

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();

        $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->fresh()->current_stage);
    }

    public function test_finished_good_pdf_uses_the_shared_fr_qac_193_doc_number_and_checkbox_disposition(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $batch->finishedCheck->update(['disposition' => FinishedCheck::DISPOSITION_ACCEPTED]);
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get("/batches/{$batch->id}/print/finished/preview")->assertOk()->getContent();

        $this->assertStringContainsString('FR.QAC.193.00', $html);
        $this->assertStringContainsString('☑ Accepted', $html);
        $this->assertStringContainsString('☐ Rejected', $html);
        // Functional Test + Bulk/Color/Odor share one "Special Inspection" group header in the
        // item list, not two separate "... Packaging" headers.
        $this->assertStringNotContainsString('Functional Test Packaging', $html);
        $this->assertStringNotContainsString('Special Test Packaging', $html);
        $this->assertStringContainsString('Tersier Packaging', $html);
    }

    public function test_legacy_filling_packing_verification_link_still_resolves(): void
    {
        $batch = $this->makeBatchAtPrintStage();

        $this->get("/verify/{$batch->id}/filling_packing")->assertOk();
    }

    public function test_filling_pdf_photo_labels_match_the_filling_form(): void
    {
        $batch = $this->makeBatchAtPrintStage();

        $response = $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print/filling/preview");

        $response->assertOk();
        foreach (FillingCheck::PHOTO_LABELS as $label) {
            $response->assertSee('<strong>'.$label.'</strong>', false);
        }
        $response->assertDontSee('Identity Bulk Bulk');
        $this->assertSame(FillingCheckController::PHOTO_FIELDS, array_keys(FillingCheck::PHOTO_LABELS));
    }

    public function test_preview_route_is_forbidden_before_batch_reaches_print_stage(): void
    {
        $batch = $this->makeBatchNotYetAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/preview")->assertForbidden();
    }

    public function test_preview_route_unknown_stage_404s(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/bogus/preview")->assertNotFound();
    }

    public function test_printing_both_full_flow_forms_advances_batch_to_completed(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        foreach (['startup', 'filling_packing_fg'] as $stage) {
            $this->actingAs($user)->get("/batches/{$batch->id}/print/{$stage}/pdf")->assertOk();
        }

        $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->fresh()->current_stage);
    }

    public function test_printing_only_two_of_three_stages_does_not_advance_batch(): void
    {
        $batch = $this->makeBatchAtPrintStage();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/filling/pdf")->assertOk();

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

        foreach (['startup', 'filling_packing_fg'] as $stage) {
            $this->actingAs($user)->get("/batches/{$batch->id}/print/{$stage}/pdf")->assertOk();
        }
        $this->assertSame(IpcBatch::STAGE_COMPLETED, $batch->fresh()->current_stage);

        $this->actingAs($user)->get("/batches/{$batch->id}/print")->assertOk();
        $this->actingAs($user)->get("/batches/{$batch->id}/print/startup/pdf")->assertOk();

        $this->assertSame(2, IpcPrintLog::query()->where('ipc_batch_id', $batch->id)->where('stage', 'startup')->count());
    }

    // --- Flow Packing + FG: Startup (182.03) / Packing (193.00) / Finished are 3 separate forms ---

    private function makePackingFgBatchAtPrintStage(): IpcBatch
    {
        $batch = $this->makeBatchAtPrintStage();
        $batch->update(['flow_type' => IpcBatch::FLOW_PACKING_FG]);
        $batch->fillingCheck()->delete();
        $batch->approvals()->where('stage', IpcApproval::STAGE_FILLING)->delete();

        return $batch->fresh();
    }

    public function test_packing_fg_flow_splits_startup_and_packing_into_separate_forms(): void
    {
        $batch = $this->makePackingFgBatchAtPrintStage();
        $viewer = User::factory()->create();

        $startup = $this->actingAs($viewer)->get("/batches/{$batch->id}/print/startup/preview")->assertOk()->getContent();
        $this->assertStringContainsString('Form Start Up', $startup);
        $this->assertStringContainsString('FR.QAC.182.03', $startup);
        $this->assertStringContainsString('Form Pemeriksaan Start Up', $startup);
        $this->assertStringNotContainsString('Packing Inspection', $startup);

        $packing = $this->actingAs($viewer)->get("/batches/{$batch->id}/print/packing/preview")->assertOk()->getContent();
        $this->assertStringContainsString('Form Packing', $packing);
        $this->assertStringContainsString('FR.QAC.193.00', $packing);
        $this->assertStringContainsString('Packing Inspection', $packing);
        $this->assertStringNotContainsString('Form Pemeriksaan Start Up', $packing);
    }

    public function test_packing_fg_flow_requires_a_startup_approval_before_print(): void
    {
        $batch = $this->makePackingFgBatchAtPrintStage();

        $this->assertSame(
            [IpcApproval::STAGE_STARTUP, IpcApproval::STAGE_PACKING, IpcApproval::STAGE_FINISHED],
            IpcApproval::requiredStagesFor($batch),
        );
    }

    public function test_full_flow_startup_form_has_no_filling_inspection_and_puts_verifikasi_above_berat_produk(): void
    {
        $batch = $this->makeBatchAtPrintStage();

        $html = $this->actingAs(User::factory()->create())->get("/batches/{$batch->id}/print/startup/preview")->assertOk()->getContent();

        $this->assertStringContainsString('Form Start Up', $html);
        $this->assertStringNotContainsString('Filling Inspection', $html);
        $this->assertStringNotContainsString('Packing Inspection', $html);
        $this->assertLessThan(
            strpos($html, '>Pemeriksaan Berat Produk<'),
            strpos($html, '>Verifikasi Sebelum Produksi<'),
        );
        $this->assertLessThan(
            strpos($html, '>Verifikasi Sebelum Produksi<'),
            strpos($html, '>Pemeriksaan Format Coding<'),
        );
    }
}
