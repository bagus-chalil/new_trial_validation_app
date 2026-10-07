<?php

namespace Tests\Feature;

use App\Actions\FillingChecks\SaveFillingCheck;
use App\Actions\FinishedChecks\SaveFinishedCheck;
use App\Actions\PackingChecks\SavePackingCheck;
use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Selesaikan pakai riwayat terakhir" on Filling / Packing / Finished Check: finalizing a stage
 * from its latest saved TH Progress round instead of re-filling the form.
 */
class CompleteStageFromHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeBatch(string $stage = IpcBatch::STAGE_FILLING): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $this->user->id,
            'current_stage' => $stage,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->user->id, 'completed_at' => now()]);

        return $batch->fresh();
    }

    private function saveFillingDraft(IpcBatch $batch, string $remarks, array $weights): void
    {
        app(SaveFillingCheck::class)->handle($batch->fresh(), $this->user, [
            'finalize' => false,
            'sample_bulk_odor_status' => 'Conform',
            'sample_leakage_test_status' => 'Not Conform',
            'remarks' => $remarks,
            'decision' => FillingCheck::DECISION_PASSED,
            'samples' => collect($weights)->map(fn ($w, $i) => ['sample_no' => $i + 1, 'weight_value' => $w])->all(),
        ]);
    }

    private function savePackingDraft(IpcBatch $batch, string $remarks): void
    {
        app(SavePackingCheck::class)->handle($batch->fresh(), $this->user, [
            'finalize' => false,
            'primary_bulk_status' => 'Conform',
            'sum_weight_mb' => 2600,
            'standard_weight_mb' => 2650,
            'line_leader_name' => 'Budi',
            'remarks' => $remarks,
            'decision' => PackingCheck::DECISIONS[0],
        ]);
    }

    public function test_filling_check_can_be_completed_from_the_latest_saved_round(): void
    {
        $batch = $this->makeBatch();
        $this->saveFillingDraft($batch, 'round 1', [15.30, 15.30]);
        $this->saveFillingDraft($batch, 'round 2', [15.20, 15.34]);

        $this->post(route('filling-check.complete', $batch))->assertRedirect(route('batches.show', $batch));

        $filling = $batch->fresh()->fillingCheck;
        $this->assertNotNull($filling->completed_at);
        $this->assertSame('round 2', $filling->remarks);
        $this->assertSame(FillingCheck::DECISION_PASSED, $filling->decision);
        $this->assertSame('Not Conform', $filling->sample_leakage_test_status);
        $this->assertEquals(15.27, (float) $filling->average_weight);
        $this->assertSame(2, $filling->samples()->count());
        // Marks the existing round as final rather than adding a new TH Progress round.
        $this->assertSame(2, $filling->save_count);
        $this->assertSame(2, $filling->revisions()->count());
        $this->assertTrue((bool) $filling->revisions()->where('revision_no', 2)->value('finalize'));
        $this->assertFalse((bool) $filling->revisions()->where('revision_no', 1)->value('finalize'));
        $this->assertSame(IpcBatch::STAGE_PACKING, $batch->fresh()->current_stage);
    }

    public function test_completing_without_any_saved_round_is_rejected(): void
    {
        $batch = $this->makeBatch();
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->user->id]);

        $this->post(route('filling-check.complete', $batch))->assertSessionHasErrors('progress');

        $this->assertNull($batch->fresh()->fillingCheck->completed_at);
    }

    public function test_an_already_completed_stage_cannot_be_completed_again(): void
    {
        $batch = $this->makeBatch();
        $this->saveFillingDraft($batch, 'round 1', [15.30]);
        $batch->fillingCheck->update(['completed_at' => now()]);

        $this->post(route('filling-check.complete', $batch))->assertForbidden();
    }

    public function test_a_user_without_edit_rights_is_forbidden(): void
    {
        $batch = $this->makeBatch();
        $this->saveFillingDraft($batch, 'round 1', [15.30]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_APPROVER]));

        $this->post(route('filling-check.complete', $batch))->assertForbidden();
        $this->assertNull($batch->fresh()->fillingCheck->completed_at);
    }

    public function test_packing_check_needs_filling_completed_first(): void
    {
        $batch = $this->makeBatch();
        $this->saveFillingDraft($batch, 'round 1', [15.30]);
        $this->savePackingDraft($batch, 'packing round 1');

        $this->post(route('packing-check.complete', $batch))->assertSessionHasErrors('progress');
        $this->assertNull($batch->fresh()->packingCheck->completed_at);
    }

    public function test_packing_check_can_be_completed_from_the_latest_saved_round(): void
    {
        $batch = $this->makeBatch();
        $this->saveFillingDraft($batch, 'round 1', [15.30]);
        $this->post(route('filling-check.complete', $batch));
        $this->savePackingDraft($batch, 'packing round 1');
        $this->savePackingDraft($batch, 'packing round 2');

        $this->post(route('packing-check.complete', $batch))->assertRedirect(route('finished-check.edit', $batch));

        $packing = $batch->fresh()->packingCheck;
        $this->assertNotNull($packing->completed_at);
        $this->assertSame('packing round 2', $packing->remarks);
        $this->assertSame(PackingCheck::DECISIONS[0], $packing->decision);
        $this->assertSame('Conform', $packing->primary_bulk_status);
        $this->assertSame(2600, (int) $packing->sum_weight_mb);
        $this->assertSame('Budi', $packing->line_leader_name);
        $this->assertTrue((bool) $packing->revisions()->where('revision_no', 2)->value('finalize'));
        $this->assertSame(IpcBatch::STAGE_FINISHED, $batch->fresh()->current_stage);
    }

    public function test_finished_check_can_be_completed_from_the_latest_saved_round(): void
    {
        $batch = $this->makeBatch();
        $this->saveFillingDraft($batch, 'round 1', [15.30]);
        $this->post(route('filling-check.complete', $batch));
        $this->savePackingDraft($batch, 'packing round 1');
        $this->post(route('packing-check.complete', $batch));

        app(SaveFinishedCheck::class)->handle($batch->fresh(), $this->user, [
            'finalize' => false,
            'quantity_wi' => 500,
            'disposition' => FinishedCheck::DISPOSITION_ACCEPTED,
            'remarks' => 'fg round 1',
            'samples' => ['tersier_identity' => ['ac' => 1, 'cd' => 0]],
        ]);

        $this->post(route('finished-check.complete', $batch))->assertRedirect(route('batches.show', $batch));

        $finished = $batch->fresh()->finishedCheck;
        $this->assertNotNull($finished->completed_at);
        $this->assertSame('fg round 1', $finished->remarks);
        $this->assertSame(FinishedCheck::DISPOSITION_ACCEPTED, $finished->disposition);
        $this->assertSame(500, (int) $finished->quantity_wi);
        $this->assertTrue((bool) $finished->revisions()->latest('revision_no')->value('finalize'));
        $this->assertSame(IpcBatch::STAGE_APPROVAL, $batch->fresh()->current_stage);
    }
}
