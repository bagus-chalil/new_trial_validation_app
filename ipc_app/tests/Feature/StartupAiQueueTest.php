<?php

namespace Tests\Feature;

use App\Actions\Vision\QueueStartupAiReads;
use App\Actions\Vision\ReadStartupPhoto;
use App\Jobs\ReadStartupPhotoJob;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcBatchBulkCode;
use App\Models\IpcLog;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Startup AI, queued: photos are read in the background (one worker = one request at a time on
 * the AI server) and the "Rekomendasi AI" card is fed by the status endpoint. Advisory only.
 * The test queue driver is `sync`, so a dispatched read runs inline against Http::fake.
 */
class StartupAiQueueTest extends TestCase
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
            'exp_date' => '2029-10-23',
        ]);
        IpcBatchBulkCode::create(['ipc_batch_id' => $batch->id, 'bulk_code' => '6124490033']);

        return $batch;
    }

    private function photo(IpcBatch $batch, string $field): IpcAttachment
    {
        Storage::disk('public')->put("ipc-attachments/{$batch->id}/startup/{$field}.jpg", 'jpeg-bytes');

        return IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'field_label' => $field,
            'file_path' => "ipc-attachments/{$batch->id}/startup/{$field}.jpg",
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function fakeVision(array ...$responses): void
    {
        $seq = Http::sequence();
        foreach ($responses as $r) {
            $seq->push($r);
        }
        Http::fake(['vision.test/api/analyze' => $seq]);
    }

    private function coding(array $overrides = []): array
    {
        return [
            'pipeline' => 'llm', 'status' => 'OK', 'batch_raw' => 'EKB', 'exp_raw' => '231029', 'exp_format' => 'DDMMYY',
            'exp_parts' => ['day' => '23', 'month' => '10', 'year' => '29'], 'packaging_type' => 'non_tube',
            'legible' => ['batch' => true, 'exp' => true], 'format_ok' => ['batch' => true, 'exp' => true],
            'view_agreement' => ['batch' => true, 'exp' => true], 'trusted' => ['batch' => true, 'exp' => true], 'confidence' => 0.95,
            ...$overrides,
        ];
    }

    private function tubeLlm(string $exp = '231029'): array
    {
        return [
            'pipeline' => 'llm', 'status' => 'OK', 'exp_raw' => $exp, 'exp_format' => 'DDMMYY', 'format_ok' => ['exp' => true],
            'trusted_sources' => 3, 'sources_read' => 4, 'sources_total' => 4, 'sources' => [], 'confidence' => 0.9, 'elapsed_sec' => 31.2,
        ];
    }

    private function queueReads(IpcBatch $batch, array $form = [])
    {
        return $this->postJson(route('startup-check.ai-queue', $batch), $form);
    }

    public function test_photos_are_read_in_the_background_and_evaluated_against_the_current_form(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding());

        $response = $this->queueReads($batch, ['exp_date' => '2029-10-23', 'packaging_type' => 'non_tube'])->assertOk();

        $response->assertJsonPath('pending', false)->assertJsonPath('fields.0.field', 'coding_primary')->assertJsonPath('fields.0.state', 'done');
        $this->assertSame('PASS', $response->json('fields.0.decision'));
        $this->assertSame(1, IpcLog::where('field_type', 'coding_print')->count());

        // The operator edits the EXP afterwards: re-evaluated from the stored read, no new Vision call.
        $this->getJson(route('startup-check.ai-status', [$batch, 'exp_date' => '2029-10-24', 'packaging_type' => 'non_tube']))
            ->assertOk()
            ->assertJsonPath('fields.0.decision', 'FAIL');
        Http::assertSentCount(1);
    }

    public function test_queueing_twice_reads_each_photo_only_once(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding());

        $this->queueReads($batch, ['packaging_type' => 'non_tube'])->assertOk();
        $this->queueReads($batch, ['packaging_type' => 'non_tube'])->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(1, IpcLog::count());
    }

    public function test_tube_reads_go_on_separate_queues_and_stay_review(): void
    {
        Queue::fake();
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');

        $this->queueReads($batch, ['packaging_type' => 'tube'])
            ->assertOk()
            ->assertJsonPath('pending', true)
            ->assertJsonPath('fields.0.state', 'queued');

        Queue::assertPushedOn(QueueStartupAiReads::QUEUE, ReadStartupPhotoJob::class, fn ($j) => $j->fieldType === 'coding_print');
        Queue::assertPushedOn(QueueStartupAiReads::QUEUE, ReadStartupPhotoJob::class, fn ($j) => $j->fieldType === 'tube_exp_date');
        Queue::assertPushedOn(QueueStartupAiReads::QUEUE_SLOW, ReadStartupPhotoJob::class, fn ($j) => $j->fieldType === 'tube_exp_llm');
        Queue::assertPushed(ReadStartupPhotoJob::class, 3);
    }

    public function test_two_tube_photos_that_agree_get_a_pair_note_and_the_result_stays_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->photo($batch, 'coding_primary_2');
        $tubeOcr = ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2];
        $tubeCoding = $this->coding(['packaging_type' => 'tube']);
        $this->fakeVision($tubeCoding, $tubeOcr, $this->tubeLlm(), $tubeCoding, $tubeOcr, $this->tubeLlm());

        $response = $this->queueReads($batch, ['exp_date' => '2029-10-23', 'packaging_type' => 'tube'])->assertOk();

        $response->assertJsonPath('pending', false);
        $this->assertCount(2, $response->json('fields'));
        $this->assertSame('REVIEW', $response->json('fields.0.decision'));
        $this->assertSame('REVIEW', $response->json('fields.1.decision'));
        $this->assertStringContainsString('terbaca sama', $response->json('notes.0'));
    }

    public function test_two_tube_photos_that_disagree_are_flagged_for_a_manual_check(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->photo($batch, 'coding_primary_2');
        $tubeOcr = ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2];
        $tubeCoding = $this->coding(['packaging_type' => 'tube']);
        $this->fakeVision($tubeCoding, $tubeOcr, $this->tubeLlm('231029'), $tubeCoding, $tubeOcr, $this->tubeLlm('231028'));

        $this->queueReads($batch, ['packaging_type' => 'tube'])
            ->assertOk()
            ->assertJsonPath('notes.0', 'Foto 1 terbaca 231029, foto ke-2 terbaca 231028: beda, cek manual.');
    }

    public function test_tube_llm_row_records_the_db_exp_and_flags_an_ok_that_differs(): void
    {
        $batch = $this->makeBatch(); // DB exp_date 2029-10-23 = 231029
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2],
            $this->tubeLlm('231028'),
        );

        $this->queueReads($batch, ['packaging_type' => 'tube'])->assertOk();

        $log = IpcLog::where('field_type', 'tube_exp_llm')->firstOrFail();
        $this->assertSame('231028', $log->ocr_value);
        $this->assertSame('REVIEW', $log->decision);
        $this->assertSame('231029', $log->comparison['db_exp']);
        $this->assertTrue($log->comparison['ai_ok_but_differs']);
    }

    public function test_vision_down_leaves_a_review_result_not_a_failure(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        Http::fake(['vision.test/api/analyze' => Http::response('down', 503)]);

        $this->queueReads($batch, ['packaging_type' => 'non_tube'])
            ->assertOk()
            ->assertJsonPath('fields.0.state', 'done')
            ->assertJsonPath('fields.0.decision', 'REVIEW');
    }

    public function test_a_replaced_photo_is_skipped_by_its_stale_job(): void
    {
        $batch = $this->makeBatch();
        $attachment = $this->photo($batch, 'coding_primary');
        Http::fake();

        $id = $attachment->id;
        $attachment->delete();
        (new ReadStartupPhotoJob($batch->id, $id, 'coding_primary', 'coding_print', auth()->id()))->handle(app(ReadStartupPhoto::class));

        Http::assertNothingSent();
        $this->assertSame(0, IpcLog::count());
    }

    public function test_it_is_404_when_the_feature_is_off(): void
    {
        config(['vision.startup_ai.enabled' => false]);
        $batch = $this->makeBatch();

        $this->queueReads($batch)->assertNotFound();
        $this->getJson(route('startup-check.ai-status', $batch))->assertNotFound();
    }
}
