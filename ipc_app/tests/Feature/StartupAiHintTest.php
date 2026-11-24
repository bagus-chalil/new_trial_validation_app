<?php

namespace Tests\Feature;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcBatchBulkCode;
use App\Models\IpcLog;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Beri masukan ke AI": the operator reads the SAME saved photo again with a short hint (where/how to
 * look), no retake. The hint only steers the Vision Service; a hinted read that comes out worse than the
 * earlier one is kept in the log but not shown. Test queue driver is `sync`, so jobs run inline.
 */
class StartupAiHintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['vision.base_url' => 'http://vision.test', 'vision.startup_ai.enabled' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function makeBatch(): IpcBatch
    {
        $product = MasterProduct::updateOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'shelf_life_months' => 36, 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => '26X005/EKB',
            'bulk_code' => '6124490033',
            'created_by' => auth()->id(),
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);
        IpcBatchBulkCode::create(['ipc_batch_id' => $batch->id, 'bulk_code' => '6124490033']);

        Storage::disk('public')->put("ipc-attachments/{$batch->id}/startup/date_bulk.jpg", 'jpeg-bytes');
        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'field_label' => 'date_bulk',
            'file_path' => "ipc-attachments/{$batch->id}/startup/date_bulk.jpg",
            'uploaded_by' => auth()->id(),
        ]);

        return $batch;
    }

    private function labelBulk(bool $ok): array
    {
        $flag = ['no_batch' => $ok, 'mixing_date' => $ok, 'kode_bulk' => $ok];

        return [
            'pipeline' => 'llm', 'status' => $ok ? 'OK' : 'LOW_CONFIDENCE', 'no_batch_raw' => '26X005/EKB', 'mixing_date_raw' => '23/10/2026',
            'kode_bulk' => '6124490033', 'legible' => $flag, 'format_ok' => $flag, 'view_agreement' => $flag, 'confidence' => $ok ? 0.95 : 0.4,
        ];
    }

    private function hint(IpcBatch $batch, array $data = [])
    {
        return $this->postJson(route('startup-check.ai-hint', $batch), ['field' => 'date_bulk', 'hint' => 'Fokus ke stiker kuning', ...$data]);
    }

    public function test_hint_rereads_the_same_photo_sends_the_hint_and_records_it(): void
    {
        $batch = $this->makeBatch();
        Http::fake(['vision.test/api/analyze' => Http::sequence()->push($this->labelBulk(false))->push($this->labelBulk(true))]);

        $this->postJson(route('startup-check.ai-queue', $batch))->assertOk();
        $response = $this->hint($batch)->assertOk()->assertJsonPath('queued', true);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->body(), 'name="user_hint"') && str_contains($request->body(), 'Fokus ke stiker kuning'));

        $log = IpcLog::query()->where('field_type', 'label_bulk')->latest('id')->first();
        $this->assertSame('Fokus ke stiker kuning', $log->comparison['user_hint']);
        $this->assertFalse($log->comparison['hint_discarded']);
        $response->assertJsonPath('fields.0.hint.text', 'Fokus ke stiker kuning')->assertJsonPath('fields.0.hint.discarded', false);
        // The better, hinted read is the one shown.
        $this->assertSame('done', $response->json('fields.0.state'));
        $this->assertSame('OK', $log->vision_status);
    }

    public function test_a_hinted_read_that_is_worse_is_kept_in_the_log_but_the_earlier_read_stays_shown(): void
    {
        $batch = $this->makeBatch();
        Http::fake(['vision.test/api/analyze' => Http::sequence()->push($this->labelBulk(true))->push($this->labelBulk(false))]);

        $this->postJson(route('startup-check.ai-queue', $batch), ['mixing_date' => '2026-10-23'])->assertOk();
        $response = $this->hint($batch, ['mixing_date' => '2026-10-23'])->assertOk();

        $this->assertSame(2, IpcLog::query()->where('field_type', 'label_bulk')->count());
        $this->assertTrue(IpcLog::query()->where('field_type', 'label_bulk')->latest('id')->first()->comparison['hint_discarded']);
        // Still the first (OK) read: PASS, with a note that the hint did not help.
        $response->assertJsonPath('fields.0.decision', 'PASS')->assertJsonPath('fields.0.hint.discarded', true);
    }

    public function test_a_failed_hinted_read_never_replaces_a_good_read(): void
    {
        $batch = $this->makeBatch();
        Http::fake(['vision.test/api/analyze' => Http::sequence()->push($this->labelBulk(true))->push(['error' => 'boom'], 500)]);

        $this->postJson(route('startup-check.ai-queue', $batch))->assertOk();
        $response = $this->hint($batch)->assertOk();

        $response->assertJsonPath('fields.0.state', 'done')->assertJsonPath('fields.0.hint.discarded', true);
    }

    public function test_hint_is_validated(): void
    {
        $batch = $this->makeBatch();
        Http::fake();

        $this->hint($batch, ['hint' => 'ab'])->assertStatus(422)->assertJsonValidationErrors('hint');
        $this->hint($batch, ['hint' => str_repeat('x', 301)])->assertStatus(422)->assertJsonValidationErrors('hint');
        $this->hint($batch, ['field' => 'coding_primary_2'])->assertStatus(422)->assertJsonValidationErrors('field');
        $this->hint($batch, ['field' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('field');
        Http::assertNothingSent();
    }

    public function test_control_characters_and_line_breaks_are_flattened_before_the_hint_is_stored(): void
    {
        $batch = $this->makeBatch();
        Http::fake(['vision.test/api/analyze' => Http::response($this->labelBulk(true))]);

        $this->hint($batch, ['hint' => "baris satu\n\nbaris   dua\x00"])->assertOk();

        $this->assertSame('baris satu baris dua', IpcLog::query()->where('field_type', 'label_bulk')->latest('id')->first()->comparison['user_hint']);
    }

    public function test_no_photo_means_nothing_is_queued(): void
    {
        $batch = $this->makeBatch();
        IpcAttachment::query()->where('ipc_batch_id', $batch->id)->delete();
        Http::fake();

        $this->hint($batch)->assertOk()->assertJsonPath('queued', false);
        Http::assertNothingSent();
    }

    public function test_feedback_is_refused_once_startup_is_finished(): void
    {
        $batch = $this->makeBatch();
        $batch->startupCheck()->create(['user_id' => auth()->id(), 'completed_at' => now()]);
        Http::fake();

        $this->hint($batch)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_feedback_endpoint_is_hidden_when_startup_ai_is_off(): void
    {
        config(['vision.startup_ai.enabled' => false]);

        $this->hint($this->makeBatch())->assertNotFound();
    }
}
