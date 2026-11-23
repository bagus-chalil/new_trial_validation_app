<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevision;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BatchSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;

    private MasterProduct $product;

    private MasterLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['vision.batch_summary.enabled' => true, 'vision.base_url' => 'http://vision.test']);

        $this->approver = User::factory()->approver()->create();
        $this->product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Lipstik A', 'is_active' => true]);
        $this->line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
    }

    private function batch(string $noBatch, string $bulkStatus, string $remarks = ''): IpcBatch
    {
        $batch = IpcBatch::create([
            'master_product_id' => $this->product->id,
            'no_batch' => $noBatch,
            'master_line_id' => $this->line->id,
            'created_by' => $this->approver->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->approver->id, 'completed_at' => now(), 'remarks' => $remarks]);
        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->approver->id, 'save_count' => 1, 'completed_at' => now()]);
        FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->approver->id, 'completed_at' => now()]);
        $packing = PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->approver->id, 'save_count' => 2, 'completed_at' => now(), 'decision' => 'Hold']);
        PackingCheckRevision::create(['packing_check_id' => $packing->id, 'revision_no' => 1, 'finalize' => true, 'user_id' => $this->approver->id, 'primary_bulk_status' => $bulkStatus]);

        return $batch->fresh();
    }

    /** @return list<string> */
    private function points(string $uri): array
    {
        $points = [];
        $this->actingAs($this->approver)->get($uri)->assertInertia(function ($page) use (&$points) {
            $points = $page->toArray()['props']['summary']['points'];
        });

        return $points;
    }

    public function test_approval_page_carries_the_rule_based_points_without_any_ai_call(): void
    {
        Http::fake();
        $this->batch('B-OLD-1', 'Not Conform');
        $this->batch('B-OLD-2', 'Not Conform');
        $batch = $this->batch('B-NOW', 'Not Conform', "  Botol   penyok\nsedikit ");

        $this->actingAs($this->approver)->get("/batches/{$batch->id}/approval")->assertInertia(fn ($page) => $page
            ->component('approval/index')
            ->where('aiSummaryEnabled', true)
            ->has('summary.findings', 1)
            ->where('summary.findings.0.repeating', true));

        $points = collect($this->points("/batches/{$batch->id}/approval"));

        $this->assertTrue($points->contains(fn ($p) => str_contains($p, 'B-NOW') && str_contains($p, 'Lipstik A') && str_contains($p, 'Make Up 01')));
        $this->assertTrue($points->contains(fn ($p) => str_contains($p, '1 item NOT OK') && str_contains($p, 'Packing · Primary Bulk')));
        $this->assertTrue($points->contains(fn ($p) => str_contains($p, 'Berulang') && str_contains($p, '2 dari 2 batch produk yang sama')));
        $this->assertTrue($points->contains(fn ($p) => str_contains($p, 'Packing 2 ronde')));
        $this->assertTrue($points->contains(fn ($p) => str_contains($p, 'Packing: Hold')));
        $this->assertTrue($points->contains('Catatan QC Startup: "Botol penyok sedikit".'));

        Http::assertNothingSent();
    }

    public function test_a_clean_batch_says_so(): void
    {
        $batch = $this->batch('B-CLEAN', 'Conform');

        $points = collect($this->points("/batches/{$batch->id}/approval"));

        $this->assertTrue($points->contains(fn ($p) => str_starts_with($p, 'Tidak ada item NOT OK')));
    }

    public function test_summary_sends_only_a_kind_and_facts_and_returns_the_paragraph(): void
    {
        Http::fake(['vision.test/api/insights/summarize' => Http::response(['status' => 'OK', 'kind' => 'ipc_batch_summary', 'text' => 'Ada satu temuan NOT OK di Packing.'])]);
        $batch = $this->batch('B-NOW', 'Not Conform');

        $this->actingAs($this->approver)->postJson("/batches/{$batch->id}/approval/summary")
            ->assertOk()
            ->assertJson(['ok' => true, 'text' => 'Ada satu temuan NOT OK di Packing.']);

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'http://vision.test/api/insights/summarize'
                && $request['kind'] === 'ipc_batch_summary'
                && is_array($request['facts'])
                && str_contains(implode(' ', $request['facts']), 'Packing · Primary Bulk')
                // This app never ships a prompt: the Vision Service owns it.
                && ! isset($request['system']) && ! isset($request['prompt']);
        });
    }

    public function test_a_second_summary_for_the_same_facts_is_served_from_cache(): void
    {
        Http::fake(['vision.test/*' => Http::response(['status' => 'OK', 'text' => 'Ringkasan.'])]);
        $batch = $this->batch('B-NOW', 'Not Conform');

        $this->actingAs($this->approver)->postJson("/batches/{$batch->id}/approval/summary")->assertOk();
        $this->actingAs($this->approver)->postJson("/batches/{$batch->id}/approval/summary")->assertOk()->assertJson(['cached' => true]);

        Http::assertSentCount(1);
    }

    public function test_failures_come_back_as_a_readable_error_not_a_crash(): void
    {
        $batch = $this->batch('B-NOW', 'Not Conform');
        $url = "/batches/{$batch->id}/approval/summary";

        // One stub, answered in order (a second Http::fake() would not override the first).
        Http::fake(['vision.test/*' => Http::sequence()
            ->push(['status' => 'ERROR'], 503)
            ->push(['status' => 'ERROR', 'error' => 'advisory'], 422)
            ->push('', 404)
            ->pushFailedConnection()]);

        $this->actingAs($this->approver)->postJson($url)->assertOk()->assertJson(['ok' => false]);
        $this->assertStringContainsString('advisory', $this->actingAs($this->approver)->postJson($url)->assertOk()->json('error'));
        $this->assertStringContainsString('belum diperbarui', $this->actingAs($this->approver)->postJson($url)->assertOk()->json('error'));
        $this->assertStringContainsString('tidak terjangkau', $this->actingAs($this->approver)->postJson($url)->assertOk()->json('error'));
    }

    public function test_summary_is_off_unless_enabled_and_closed_to_non_approvers(): void
    {
        Http::fake();
        $batch = $this->batch('B-NOW', 'Not Conform');

        config(['vision.batch_summary.enabled' => false]);
        $this->actingAs($this->approver)->postJson("/batches/{$batch->id}/approval/summary")->assertNotFound();

        config(['vision.batch_summary.enabled' => true]);
        $this->actingAs(User::factory()->create())->postJson("/batches/{$batch->id}/approval/summary")->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_trivial_remarks_are_left_out_but_real_ones_stay(): void
    {
        $batch = $this->batch('B-REMARK', 'Conform', 'OK.');
        $batch->packingCheck->update(['remarks' => 'Tutup sedikit longgar di round 2']);

        $points = collect($this->points("/batches/{$batch->id}/approval"));

        $this->assertFalse($points->contains(fn ($p) => str_contains($p, 'Catatan QC Startup')));
        $this->assertTrue($points->contains('Catatan QC Packing: "Tutup sedikit longgar di round 2".'));
    }
}
