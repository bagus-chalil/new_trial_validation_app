<?php

namespace Tests\Feature;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the public verification page (VerificationController) a printed IPC report's QR code
 * links to (see App\Services\Verification\VerificationQrCode) — the QR itself is verified by
 * ApprovalTest/PrintTest asserting the PDF blade views render without error; this file covers
 * what scanning it actually lands on.
 */
class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->create();

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
        ]);
    }

    public function test_guests_can_view_the_verification_page(): void
    {
        $batch = $this->makeBatch();

        $this->get("/verify/{$batch->id}/finished")->assertOk();
    }

    public function test_shows_pending_state_when_no_approval_exists_yet(): void
    {
        $batch = $this->makeBatch();

        $this->get("/verify/{$batch->id}/finished")
            ->assertOk()
            ->assertSee('Belum Ada Approval');
    }

    public function test_shows_approved_state_with_approver_name_and_time(): void
    {
        $batch = $this->makeBatch();
        $approver = User::factory()->create(['name' => 'Approver Satu']);

        IpcApproval::create([
            'ipc_batch_id' => $batch->id,
            'stage' => IpcApproval::STAGE_FINISHED,
            'decision' => IpcApproval::DECISION_APPROVED,
            'approver_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->get("/verify/{$batch->id}/finished")
            ->assertOk()
            ->assertSee('Dokumen Terverifikasi')
            ->assertSee('Approver Satu');
    }

    public function test_shows_rejected_state_with_remarks(): void
    {
        $batch = $this->makeBatch();
        $approver = User::factory()->create(['name' => 'Approver Dua']);

        IpcApproval::create([
            'ipc_batch_id' => $batch->id,
            'stage' => IpcApproval::STAGE_FINISHED,
            'decision' => IpcApproval::DECISION_REJECTED,
            'approver_user_id' => $approver->id,
            'approved_at' => now(),
            'remarks' => 'Kemasan penyok',
        ]);

        $this->get("/verify/{$batch->id}/finished")
            ->assertOk()
            ->assertSee('Dokumen Ditolak')
            ->assertSee('Kemasan penyok');
    }

    public function test_only_looks_at_the_approval_for_the_requested_stage(): void
    {
        $batch = $this->makeBatch();
        $approver = User::factory()->create();

        IpcApproval::create([
            'ipc_batch_id' => $batch->id,
            'stage' => IpcApproval::STAGE_STARTUP,
            'decision' => IpcApproval::DECISION_APPROVED,
            'approver_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->get("/verify/{$batch->id}/finished")
            ->assertOk()
            ->assertSee('Belum Ada Approval');
    }

    public function test_soft_deleted_batch_is_not_found(): void
    {
        $batch = $this->makeBatch();
        $batch->delete();

        $this->get("/verify/{$batch->id}/finished")->assertNotFound();
    }

    public function test_unknown_stage_404s(): void
    {
        $batch = $this->makeBatch();

        $this->get("/verify/{$batch->id}/not-a-real-stage")->assertNotFound();
    }
}
