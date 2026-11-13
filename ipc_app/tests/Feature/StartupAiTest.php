<?php

namespace Tests\Feature;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcBatchBulkCode;
use App\Models\IpcLog;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Startup AI cross-check: advisory read of Label Bulk / IM / Primary Coding (see AnalyzeStartupPhoto). */
class StartupAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['vision.base_url' => 'http://vision.test', 'vision.startup_ai.enabled' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function makeBatch(?int $shelfLife = 36): IpcBatch
    {
        $product = MasterProduct::updateOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'shelf_life_months' => $shelfLife, 'is_active' => true]);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => '26X005/EKB',
            'bulk_code' => '6124490033',
            'created_by' => auth()->id(),
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);
        IpcBatchBulkCode::create(['ipc_batch_id' => $batch->id, 'bulk_code' => '6124490033']);

        return $batch;
    }

    private function photo(IpcBatch $batch, string $field, string $stage = 'startup'): void
    {
        Storage::disk('public')->put("ipc-attachments/{$batch->id}/{$stage}/{$field}.jpg", 'jpeg-bytes');
        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => $stage,
            'field_label' => $field,
            'file_path' => "ipc-attachments/{$batch->id}/{$stage}/{$field}.jpg",
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

    private function labelBulk(array $overrides = []): array
    {
        return [
            'pipeline' => 'llm',
            'status' => 'OK',
            'no_batch_raw' => '26X005/EKB',
            'mixing_date_raw' => '23/10/2026',
            'kode_bulk' => '6124490033',
            'legible' => ['no_batch' => true, 'mixing_date' => true, 'kode_bulk' => true],
            'format_ok' => ['no_batch' => true, 'mixing_date' => true, 'kode_bulk' => true],
            'view_agreement' => ['no_batch' => true, 'mixing_date' => true, 'kode_bulk' => true],
            'confidence' => 0.95,
            ...$overrides,
        ];
    }

    private function coding(array $overrides = []): array
    {
        return [
            'pipeline' => 'llm',
            'status' => 'OK',
            'batch_raw' => 'EKB',
            'exp_raw' => '231029',
            'exp_format' => 'DDMMYY',
            'exp_parts' => ['day' => '23', 'month' => '10', 'year' => '29'],
            'packaging_type' => 'non_tube',
            'legible' => ['batch' => true, 'exp' => true],
            'format_ok' => ['batch' => true, 'exp' => true],
            'view_agreement' => ['batch' => true, 'exp' => true],
            'trusted' => ['batch' => true, 'exp' => true],
            'confidence' => 0.95,
            ...$overrides,
        ];
    }

    private function tubeLlm(array $overrides = []): array
    {
        return [
            'pipeline' => 'llm',
            'status' => 'OK',
            'exp_raw' => '231029',
            'exp_format' => 'DDMMYY',
            'format_ok' => ['exp' => true],
            'trusted_sources' => 3,
            'sources_read' => 4,
            'sources_total' => 4,
            'sources' => [],
            'confidence' => 0.9,
            'elapsed_sec' => 31.2,
            ...$overrides,
        ];
    }

    private function read(IpcBatch $batch, string $field, array $form = [])
    {
        return $this->postJson(route('startup-check.ai-read', $batch), ['field' => $field, ...$form]);
    }

    public function test_tube_llm_read_is_advisory_logged_and_never_passes(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2],
            $this->tubeLlm(['exp_raw' => '231028']),
        );

        $response = $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23', 'packaging_type' => 'tube'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');

        $item = collect($response->json('items'))->firstWhere('key', 'tube_exp_llm');
        $this->assertSame('231028', $item['read']);
        $this->assertSame('231029', $item['expected']);
        $this->assertSame('info', $item['verdict']);
        $this->assertStringContainsString('BEDA', $item['note']);

        $log = IpcLog::where('field_type', 'tube_exp_llm')->firstOrFail();
        $this->assertSame('231028', $log->ocr_value);
        $this->assertSame('REVIEW', $log->decision);
        $this->assertTrue($log->comparison['advisory']);
        // AI status OK but 231028 != form EXP 231029: flagged for an eye check.
        $this->assertTrue($log->comparison['ai_ok_but_differs']);
        $this->assertSame(3, $log->vision_response['trusted_sources']);
    }

    public static function tubeLlmNonOkProvider(): array
    {
        return [
            'low confidence' => [['pipeline' => 'llm', 'status' => 'LOW_CONFIDENCE', 'exp_raw' => null, 'trusted_sources' => 1, 'sources_total' => 4]],
            'error' => [['pipeline' => 'llm', 'status' => 'ERROR', 'error_reason' => 'LLM_UNAVAILABLE: down']],
        ];
    }

    #[DataProvider('tubeLlmNonOkProvider')]
    public function test_tube_llm_low_confidence_and_error_are_review_not_fail(array $llm): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2],
            $llm,
        );

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23', 'packaging_type' => 'tube'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');

        $this->assertSame(1, IpcLog::where('field_type', 'tube_exp_llm')->where('decision', 'REVIEW')->count());
    }

    public function test_tube_llm_uses_the_longer_llm_timeout(): void
    {
        config(['vision.timeout' => 90, 'vision.llm_timeout' => 150]);
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2],
            $this->tubeLlm(),
        );

        $this->read($batch, 'coding_primary', ['packaging_type' => 'tube'])->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'tube_exp_llm'));
    }

    public function test_tube_llm_can_be_turned_off(): void
    {
        config(['vision.startup_ai.tube_llm_field_type' => null]);
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2],
        );

        $this->read($batch, 'coding_primary', ['packaging_type' => 'tube'])->assertOk();

        $this->assertSame(0, IpcLog::where('field_type', 'tube_exp_llm')->count());
    }

    public function test_second_tube_photo_is_read_on_its_own_and_logged_separately(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->photo($batch, 'coding_primary_2');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'LOW_CONFIDENCE', 'confidence' => 0.2],
            $this->tubeLlm(['exp_raw' => '231029']),
        );

        $this->read($batch, 'coding_primary_2', ['exp_date' => '2029-10-23', 'packaging_type' => 'tube'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');

        $this->assertTrue(IpcLog::where('field_type', 'tube_exp_llm')->where('image_path', 'like', '%coding_primary_2%')->exists());
    }

    public function test_it_is_off_by_default(): void
    {
        config(['vision.startup_ai.enabled' => false]);
        $batch = $this->makeBatch();
        $this->photo($batch, 'date_bulk');

        $this->read($batch, 'date_bulk')->assertNotFound();
    }

    public function test_it_needs_a_saved_photo(): void
    {
        $this->read($this->makeBatch(), 'im_number')->assertStatus(422);
    }

    public function test_unknown_field_is_rejected(): void
    {
        $this->read($this->makeBatch(), 'color')->assertStatus(422);
    }

    public function test_label_bulk_that_matches_everything_is_pass_and_logged(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'date_bulk');
        $this->fakeVision($this->labelBulk());

        $this->read($batch, 'date_bulk', ['mixing_date' => '2026-10-23'])
            ->assertOk()
            ->assertJsonPath('decision', 'PASS')
            ->assertJsonCount(3, 'items');

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'label_bulk'));
        $log = IpcLog::first();
        $this->assertSame('label_bulk', $log->field_type);
        $this->assertSame('startup', $log->stage);
        $this->assertSame('PASS', $log->decision);
        $this->assertSame('2026-10-23', $log->comparison['entered']['mixing_date']);
        $this->assertCount(3, $log->comparison['items']);
    }

    public function test_label_bulk_trusted_mismatch_is_fail(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'date_bulk');
        $this->fakeVision($this->labelBulk());

        $this->read($batch, 'date_bulk', ['mixing_date' => '2026-10-24'])
            ->assertOk()
            ->assertJsonPath('decision', 'FAIL');
    }

    public function test_label_bulk_untrusted_value_is_review_never_fail(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'date_bulk');
        $this->fakeVision($this->labelBulk([
            'mixing_date_raw' => '24/10/2026',
            'view_agreement' => ['no_batch' => true, 'mixing_date' => false, 'kode_bulk' => true],
        ]));

        $this->read($batch, 'date_bulk', ['mixing_date' => '2026-10-23'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');
    }

    public function test_label_bulk_without_typed_mixing_date_is_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'date_bulk');
        $this->fakeVision($this->labelBulk());

        $this->read($batch, 'date_bulk')->assertOk()->assertJsonPath('decision', 'REVIEW');
    }

    public function test_label_bulk_falls_back_to_a_photo_uploaded_on_filling_check(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'date_bulk', 'filling');
        $this->fakeVision($this->labelBulk());

        $this->read($batch, 'date_bulk', ['mixing_date' => '2026-10-23'])->assertOk()->assertJsonPath('decision', 'PASS');
    }

    public function test_im_shelf_life_matching_master_product_and_exp_formula_is_pass(): void
    {
        $batch = $this->makeBatch(36);
        $this->photo($batch, 'im_number');
        $this->fakeVision([
            'pipeline' => 'llm', 'status' => 'OK', 'im_number' => 'IM-123',
            'shelf_life_value' => ['years' => 3, 'months' => null],
            'legible' => ['im_number' => true, 'shelf_life' => true],
            'format_ok' => ['im_number' => true, 'shelf_life' => true],
            'view_agreement' => ['im_number' => true, 'shelf_life' => true],
        ]);

        $this->read($batch, 'im_number', ['mixing_date' => '2026-10-23', 'exp_date' => '2029-10-23'])
            ->assertOk()
            ->assertJsonPath('decision', 'PASS');
    }

    public function test_im_exp_that_is_not_mixing_plus_shelf_life_is_fail(): void
    {
        $batch = $this->makeBatch(36);
        $this->photo($batch, 'im_number');
        $this->fakeVision([
            'pipeline' => 'llm', 'status' => 'OK', 'im_number' => 'IM-123',
            'shelf_life_value' => ['years' => 3, 'months' => null],
            'legible' => ['im_number' => true, 'shelf_life' => true],
            'format_ok' => ['im_number' => true, 'shelf_life' => true],
            'view_agreement' => ['im_number' => true, 'shelf_life' => true],
        ]);

        $this->read($batch, 'im_number', ['mixing_date' => '2026-10-23', 'exp_date' => '2029-11-23'])
            ->assertOk()
            ->assertJsonPath('decision', 'FAIL');
    }

    public function test_im_without_master_shelf_life_is_review(): void
    {
        $batch = $this->makeBatch(null);
        $this->photo($batch, 'im_number');
        $this->fakeVision([
            'pipeline' => 'llm', 'status' => 'OK', 'im_number' => 'IM-123',
            'shelf_life_value' => ['years' => 3, 'months' => null],
            'legible' => ['im_number' => true, 'shelf_life' => true],
            'format_ok' => ['im_number' => true, 'shelf_life' => true],
            'view_agreement' => ['im_number' => true, 'shelf_life' => true],
        ]);

        $this->read($batch, 'im_number')->assertOk()->assertJsonPath('decision', 'REVIEW');
    }

    public function test_exp_formula_clamps_a_nonexistent_day_to_month_end(): void
    {
        $batch = $this->makeBatch(1);
        $this->photo($batch, 'im_number');
        $this->fakeVision([
            'pipeline' => 'llm', 'status' => 'OK', 'im_number' => 'IM-123',
            'shelf_life_value' => ['years' => null, 'months' => 1],
            'legible' => ['im_number' => true, 'shelf_life' => true],
            'format_ok' => ['im_number' => true, 'shelf_life' => true],
            'view_agreement' => ['im_number' => true, 'shelf_life' => true],
        ]);

        $this->read($batch, 'im_number', ['mixing_date' => '2026-01-31', 'exp_date' => '2026-02-28'])
            ->assertOk()
            ->assertJsonPath('decision', 'PASS');
    }

    public function test_non_tube_coding_that_matches_is_pass(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding());

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23'])->assertOk()->assertJsonPath('decision', 'PASS');
    }

    public function test_coding_month_year_exp_only_compares_month_and_year(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding(['exp_raw' => '10/2029', 'exp_format' => 'MM_YYYY', 'exp_parts' => ['day' => null, 'month' => '10', 'year' => '2029']]));

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23'])->assertOk()->assertJsonPath('decision', 'PASS');
    }

    public function test_coding_batch_mismatch_is_fail(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding(['batch_raw' => 'AKB']));

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23'])->assertOk()->assertJsonPath('decision', 'FAIL');
    }

    public function test_tube_is_always_review_even_when_every_value_matches(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'OK', 'extracted_date_code' => '231029', 'format_valid' => true, 'confidence' => 0.97],
            $this->tubeLlm(),
        );

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');
    }

    public function test_chosen_tube_forces_review_even_if_ai_says_non_tube(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(
            $this->coding(['packaging_type' => 'non_tube']),
            ['pipeline' => 'tube_emboss', 'status' => 'OK', 'extracted_date_code' => '231029', 'format_valid' => true, 'confidence' => 0.97],
            $this->tubeLlm(),
        );

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23', 'packaging_type' => 'tube'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');
    }

    public function test_chosen_non_tube_with_a_contradicting_ai_is_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding(['packaging_type' => 'tube']));

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23', 'packaging_type' => 'non_tube'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');
    }

    public function test_chosen_non_tube_is_trusted_when_the_ai_is_unsure_of_the_type(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding(['packaging_type' => 'unknown']));

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23', 'packaging_type' => 'non_tube'])
            ->assertOk()
            ->assertJsonPath('decision', 'PASS');
    }

    public function test_unknown_packaging_type_is_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->coding(['packaging_type' => 'unknown']));

        $this->read($batch, 'coding_primary', ['exp_date' => '2029-10-23'])->assertOk()->assertJsonPath('decision', 'REVIEW');
    }

    public function test_vision_service_down_is_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'date_bulk');
        Http::fake(['vision.test/api/analyze' => Http::response('boom', 500)]);

        $this->read($batch, 'date_bulk', ['mixing_date' => '2026-10-23'])
            ->assertOk()
            ->assertJsonPath('decision', 'REVIEW');
        $this->assertSame('ERROR', IpcLog::first()->vision_status);
    }
}
