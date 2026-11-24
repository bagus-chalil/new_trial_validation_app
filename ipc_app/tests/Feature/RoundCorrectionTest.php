<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\FillingCheckRevision;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckRevision;
use App\Models\IpcBatch;
use App\Models\IpcRoundCorrection;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevision;
use App\Models\PackingCheckRevisionPhoto;
use App\Models\StartupCheck;
use App\Models\StartupInspection;
use App\Models\User;
use App\Support\RoundAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Minta Revisi" at TH Progress level: flag a saved round, QC corrects it in place, everything audited. */
class RoundCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function batchWithRounds(): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->create();

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-ROUND',
            'master_line_id' => $line->id,
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);
        StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 1, 'completed_at' => now()]);
        FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'completed_at' => now()]);

        $packing = PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $user->id, 'save_count' => 3, 'completed_at' => now(), 'sum_weight_mb' => 7365, 'decision' => 'Passed', 'remarks' => 'ok']);
        foreach ([1 => 'Passed', 2 => 'Reject', 3 => 'Passed'] as $no => $decision) {
            PackingCheckRevision::create([
                'packing_check_id' => $packing->id, 'revision_no' => $no, 'finalize' => $no === 3,
                'sum_weight_mb' => 7000 + $no, 'decision' => $decision, 'remarks' => "ronde {$no}", 'user_id' => $user->id,
            ]);
        }

        return $batch->fresh();
    }

    private function requestRevision(IpcBatch $batch, array $payload = []): void
    {
        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/packing", [
                'decision' => 'Revision',
                'remarks' => 'TH 2 ditolak, perbaiki.',
                'revise_rounds' => ['packing' => [2]],
                ...$payload,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_approver_flags_a_specific_round_and_an_open_correction_is_recorded(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch);

        $correction = IpcRoundCorrection::sole();
        $this->assertSame('packing', $correction->stage);
        $this->assertSame(2, $correction->revision_no);
        $this->assertSame('open', $correction->status);
        $this->assertSame('TH 2 ditolak, perbaiki.', $correction->request_note);
        $this->assertNotNull($correction->requested_by);
        $this->assertNull($batch->fresh()->packingCheck->completed_at);
    }

    public function test_flagging_a_round_that_does_not_exist_is_rejected(): void
    {
        $batch = $this->batchWithRounds();

        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/packing", [
                'decision' => 'Revision', 'remarks' => 'x', 'revise_rounds' => ['packing' => [9]],
            ])
            ->assertSessionHasErrors('revise_rounds');

        $this->assertSame(0, IpcRoundCorrection::count());
        $this->assertNotNull($batch->fresh()->packingCheck->completed_at);
    }

    public function test_finalizing_is_blocked_until_the_flagged_round_is_corrected(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch);

        $this->actingAs(User::factory()->create())
            ->post("/batches/{$batch->id}/packing-check/complete")
            ->assertSessionHasErrors('progress');

        $this->assertNull($batch->fresh()->packingCheck->completed_at);
    }

    public function test_qc_corrects_the_round_in_place_and_before_after_is_audited(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch);
        $correction = IpcRoundCorrection::sole();
        $qc = User::factory()->create();

        $payload = ['correction_reason' => 'Berat MB salah ketik.', 'sum_weight_mb' => 7368, 'decision' => 'Passed', 'remarks' => 'sudah dikoreksi'];
        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $key) {
                $payload[$key] = $group['options'][0];
            }
        }

        $this->actingAs($qc)
            ->put("/batches/{$batch->id}/corrections/{$correction->id}", $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('packing-check.edit', $batch));

        $round2 = PackingCheckRevision::where('revision_no', 2)->sole();
        $this->assertSame(7368, $round2->sum_weight_mb);
        $this->assertSame('Passed', $round2->decision);
        // Other rounds untouched.
        $this->assertSame(7001, PackingCheckRevision::where('revision_no', 1)->value('sum_weight_mb'));
        $this->assertSame(7003, PackingCheckRevision::where('revision_no', 3)->value('sum_weight_mb'));

        $correction->refresh();
        $this->assertSame('done', $correction->status);
        $this->assertSame($qc->id, $correction->corrected_by);
        $this->assertSame('Berat MB salah ketik.', $correction->correction_reason);
        $this->assertSame(7002, (int) $correction->before_data['revision']['sum_weight_mb']);
        $this->assertSame('Reject', $correction->before_data['revision']['decision']);
        $this->assertSame(7368, (int) $correction->after_data['revision']['sum_weight_mb']);
        $this->assertSame('Passed', $correction->after_data['revision']['decision']);

        // With nothing left open, QC can finalize again.
        $this->actingAs($qc)->post("/batches/{$batch->id}/packing-check/complete")->assertSessionHasNoErrors();
        $this->assertNotNull($batch->fresh()->packingCheck->completed_at);
    }

    public function test_correction_requires_a_reason(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch);
        $correction = IpcRoundCorrection::sole();

        $this->actingAs(User::factory()->create())
            ->put("/batches/{$batch->id}/corrections/{$correction->id}", ['sum_weight_mb' => 7368])
            ->assertSessionHasErrors('correction_reason');

        $this->assertSame('open', $correction->fresh()->status);
    }

    public function test_correcting_the_latest_round_keeps_the_live_row_in_line(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch, ['revise_rounds' => ['packing' => [3]]]);
        $correction = IpcRoundCorrection::sole();

        $payload = ['correction_reason' => 'Koreksi.', 'sum_weight_mb' => 9999, 'decision' => 'Passed', 'remarks' => 'x'];
        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $key) {
                $payload[$key] = $group['options'][0];
            }
        }

        $this->actingAs(User::factory()->create())->put("/batches/{$batch->id}/corrections/{$correction->id}", $payload)->assertSessionHasNoErrors();

        $this->assertSame(9999, $batch->fresh()->packingCheck->sum_weight_mb);
    }

    public function test_a_done_correction_cannot_be_edited_again(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch);
        $correction = IpcRoundCorrection::sole();
        $correction->update(['status' => 'done']);

        $this->actingAs(User::factory()->create())
            ->get("/batches/{$batch->id}/corrections/{$correction->id}")
            ->assertForbidden();
    }

    public function test_open_correction_page_renders_for_qc(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch);
        $correction = IpcRoundCorrection::sole();

        $this->actingAs(User::factory()->create())
            ->get("/batches/{$batch->id}/corrections/{$correction->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('corrections/edit')
                ->where('stage', 'packing')
                ->where('values.sum_weight_mb', 7002));
    }

    public function test_filling_round_correction_replaces_samples_and_recomputes_the_average(): void
    {
        $batch = $this->batchWithRounds();
        $filling = $batch->fillingCheck;
        $revision = FillingCheckRevision::create([
            'filling_check_id' => $filling->id, 'revision_no' => 1, 'finalize' => true,
            'sample_bulk_odor_status' => 'Conform', 'sample_leakage_test_status' => 'Conform',
            'remarks' => 'ok', 'decision' => 'Passed', 'average_weight' => 10, 'user_id' => $filling->user_id,
        ]);
        $revision->samples()->createMany([['sample_no' => 1, 'weight_value' => 10], ['sample_no' => 2, 'weight_value' => 10]]);

        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/filling", [
                'decision' => 'Revision', 'remarks' => 'Sampel 2 salah.', 'revise_rounds' => ['filling' => [1]],
            ])
            ->assertSessionHasNoErrors();

        $correction = IpcRoundCorrection::where('stage', 'filling')->sole();

        $this->actingAs(User::factory()->create())
            ->put("/batches/{$batch->id}/corrections/{$correction->id}", [
                'correction_reason' => 'Sampel 2 salah ketik.',
                'sample_bulk_odor_status' => 'Conform', 'sample_leakage_test_status' => 'Conform',
                'remarks' => 'ok', 'decision' => 'Passed',
                'samples' => [['sample_no' => 1, 'weight_value' => '10'], ['sample_no' => 2, 'weight_value' => '12,5']],
            ])
            ->assertSessionHasNoErrors();

        $revision->refresh();
        $this->assertSame('11.25', $revision->average_weight);
        $this->assertSame(['10.00', '12.50'], $revision->samples()->orderBy('sample_no')->get()->map(fn ($s) => (string) $s->weight_value)->all());

        $correction->refresh();
        $this->assertSame('12.50', (string) collect($correction->after_data['samples'])->firstWhere('sample_no', 2)['weight_value']);
    }

    public function test_startup_reopen_keeps_a_before_snapshot_and_closes_it_when_refinalized(): void
    {
        $batch = $this->batchWithRounds();

        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Revision', 'remarks' => 'Foto IM buram.'])
            ->assertSessionHasNoErrors();

        $row = IpcRoundCorrection::where('stage', 'startup')->sole();
        $this->assertNull($row->revision_no);
        $this->assertSame('open', $row->status);
        $this->assertNotNull($row->before_data['startup_check']);
        // A Startup reopen is not a TH round: it must not block finalizing anything.
        $this->assertSame(0, $batch->openRoundCorrections('startup')->count());
    }

    /** A revision requested before round-level flagging existed has no correction rows: QC adds one. */
    public function test_qc_can_request_a_round_correction_on_an_old_open_revision(): void
    {
        $batch = $this->batchWithRounds();
        // Old-style request: stage reopened + Revision decision, but no round flagged.
        $this->requestRevision($batch, ['revise_rounds' => []]);
        $this->assertSame(0, IpcRoundCorrection::count());

        $qc = User::factory()->create();
        $this->actingAs($qc)
            ->post("/batches/{$batch->id}/corrections", ['stage' => 'packing', 'revision_no' => 2])
            ->assertRedirect();

        $row = IpcRoundCorrection::sole();
        $this->assertSame($qc->id, $row->requested_by);
        $this->assertSame(2, $row->revision_no);
        $this->assertSame('TH 2 ditolak, perbaiki.', $row->request_note);

        // Asking twice does not duplicate it; an unknown round or a closed stage is refused.
        $this->actingAs($qc)->post("/batches/{$batch->id}/corrections", ['stage' => 'packing', 'revision_no' => 2]);
        $this->assertSame(1, IpcRoundCorrection::count());
        $this->actingAs($qc)->post("/batches/{$batch->id}/corrections", ['stage' => 'packing', 'revision_no' => 9])->assertNotFound();
        $this->actingAs($qc)->post("/batches/{$batch->id}/corrections", ['stage' => 'filling', 'revision_no' => 1])->assertForbidden();
    }

    public function test_correction_cannot_be_requested_without_an_open_revision(): void
    {
        $batch = $this->batchWithRounds();

        $this->actingAs(User::factory()->create())
            ->post("/batches/{$batch->id}/corrections", ['stage' => 'packing', 'revision_no' => 2])
            ->assertForbidden();
    }

    public function test_cancelling_drops_the_request_and_unblocks_finalizing(): void
    {
        $batch = $this->batchWithRounds();
        $this->requestRevision($batch);
        $correction = IpcRoundCorrection::sole();
        $qc = User::factory()->create();

        $this->actingAs($qc)->post("/batches/{$batch->id}/corrections/{$correction->id}/cancel")->assertRedirect();

        $this->assertSame('cancelled', $correction->fresh()->status);
        $this->actingAs($qc)->post("/batches/{$batch->id}/packing-check/complete")->assertSessionHasNoErrors();
        $this->assertNotNull($batch->fresh()->packingCheck->completed_at);
    }

    public function test_replacing_a_round_photo_keeps_the_original_in_the_audit_and_cancel_restores_it(): void
    {
        Storage::fake('public');
        $batch = $this->batchWithRounds();
        $revision = PackingCheckRevision::where('revision_no', 2)->sole();
        PackingCheckRevisionPhoto::create(['packing_check_revision_id' => $revision->id, 'field_label' => 'color', 'file_path' => 'old/color.jpg']);

        $this->requestRevision($batch);
        $correction = IpcRoundCorrection::sole();
        $qc = User::factory()->create();

        $this->actingAs($qc)
            ->post("/batches/{$batch->id}/corrections/{$correction->id}/photo/color", ['photo' => UploadedFile::fake()->image('new.jpg')])
            ->assertSessionHasNoErrors();

        $photoPath = fn () => PackingCheckRevisionPhoto::where('packing_check_revision_id', $revision->id)->where('field_label', 'color')->value('file_path');
        $this->assertNotSame('old/color.jpg', $photoPath());
        $this->assertSame('old/color.jpg', $correction->fresh()->before_data['photos']['color']);

        // Cancelling puts the original photo back.
        $this->actingAs($qc)->post("/batches/{$batch->id}/corrections/{$correction->id}/cancel");
        $this->assertSame('old/color.jpg', $photoPath());
    }

    public function test_photo_change_is_part_of_the_saved_audit(): void
    {
        Storage::fake('public');
        $batch = $this->batchWithRounds();
        $revision = PackingCheckRevision::where('revision_no', 2)->sole();
        PackingCheckRevisionPhoto::create(['packing_check_revision_id' => $revision->id, 'field_label' => 'color', 'file_path' => 'old/color.jpg']);
        $this->requestRevision($batch);
        $correction = IpcRoundCorrection::sole();
        $qc = User::factory()->create();

        $this->actingAs($qc)->post("/batches/{$batch->id}/corrections/{$correction->id}/photo/color", ['photo' => UploadedFile::fake()->image('new.jpg')]);

        $payload = ['correction_reason' => 'Foto warna salah.', 'sum_weight_mb' => 7002, 'decision' => 'Reject', 'remarks' => 'ronde 2'];
        foreach (PackingCheck::checklistGroups() as $group) {
            foreach (array_keys($group['fields']) as $key) {
                $payload[$key] = $group['options'][0];
            }
        }
        $this->actingAs($qc)->put("/batches/{$batch->id}/corrections/{$correction->id}", $payload)->assertSessionHasNoErrors();

        $correction->refresh();
        $this->assertSame('old/color.jpg', $correction->before_data['photos']['color']);
        $this->assertNotSame('old/color.jpg', $correction->after_data['photos']['color']);
        $changes = collect(RoundAudit::forBatch($batch->fresh())['history'][0]['changes']);
        $this->assertTrue($changes->contains(fn ($c) => $c['field'] === 'foto color'));
    }

    public function test_finished_round_correction_fixes_the_round_and_is_audited(): void
    {
        $batch = $this->batchWithRounds();
        $finished = $batch->finishedCheck;
        $finished->update(['save_count' => 2]);
        foreach ([1, 2] as $no) {
            $revision = FinishedCheckRevision::create([
                'finished_check_id' => $finished->id, 'revision_no' => $no, 'finalize' => $no === 2,
                'quantity_wi' => 1000, 'masterbox' => '1,2', 'no_pallet_qty' => '1/1000',
                'disposition' => 'Rejected', 'remarks' => "ronde {$no}", 'user_id' => $finished->user_id,
            ]);
            $revision->samples()->create(['parameter_key' => 'tersier_identity', 'ac' => 1, 'cd' => 0, 'md' => 0, 'mnd' => 0]);
        }

        $this->actingAs(User::factory()->approver()->create())
            ->put("/batches/{$batch->id}/approval/finished", [
                'decision' => 'Revision', 'remarks' => 'TH 1 salah.', 'revise_rounds' => ['finished' => [1]],
            ])
            ->assertSessionHasNoErrors();
        $correction = IpcRoundCorrection::where('stage', 'finished')->sole();

        // Finalizing Finished is blocked while the round is open.
        $qc = User::factory()->create();
        $this->actingAs($qc)->post("/batches/{$batch->id}/finished-check/complete")->assertSessionHasErrors('progress');

        $this->actingAs($qc)
            ->put("/batches/{$batch->id}/corrections/{$correction->id}", [
                'correction_reason' => 'Disposisi salah pilih.',
                'quantity_wi' => 2000, 'masterbox' => '1,2,3', 'no_pallet_qty' => '1/2000',
                'disposition' => 'Accepted', 'remarks' => 'dikoreksi',
                'samples' => ['tersier_identity' => ['ac' => 2, 'cd' => 0, 'md' => 0, 'mnd' => 0]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('finished-check.edit', $batch));

        $revision = FinishedCheckRevision::where('revision_no', 1)->sole();
        $this->assertSame('Accepted', $revision->disposition);
        $this->assertSame(2000, $revision->quantity_wi);
        // Sampling quantities follow Quantity WI through the AQL table, as in a normal save.
        $this->assertNotNull($revision->quantity_sampling_aql);
        $this->assertSame(2, (int) $revision->samples()->where('parameter_key', 'tersier_identity')->value('ac'));
        // Round 2 is untouched.
        $this->assertSame('Rejected', FinishedCheckRevision::where('revision_no', 2)->value('disposition'));

        $correction->refresh();
        $this->assertSame('Rejected', $correction->before_data['revision']['disposition']);
        $this->assertSame('Accepted', $correction->after_data['revision']['disposition']);
    }
}
