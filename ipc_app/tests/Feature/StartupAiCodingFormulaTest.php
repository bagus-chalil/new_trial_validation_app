<?php

namespace Tests\Feature;

use App\Actions\Vision\RecordStartupAiFeedback;
use App\Models\IpcAiFieldFeedback;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Startup Primary Coding through the Vision Service `coding_formula` field_type (the default):
 * one call per batch with photo 1 + optional photo 2 (file2) + the SKU formula (or "auto").
 * Advisory only; anything that is not a trusted OK read is REVIEW. The Vision Service is faked.
 */
class StartupAiCodingFormulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['vision.base_url' => 'http://vision.test', 'vision.startup_ai.enabled' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function makeBatch(?string $formula = null, string $noBatch = '26X005/EKB'): IpcBatch
    {
        $product = MasterProduct::create([
            'fg_code' => 'FG-'.uniqid(), 'product_name' => 'Product 1', 'shelf_life_months' => 36,
            'coding_formula' => $formula, 'is_active' => true,
        ]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => $noBatch,
            'bulk_code' => '6124490033',
            'created_by' => auth()->id(),
            'current_stage' => IpcBatch::STAGE_STARTUP,
            'exp_date' => '2029-10-23',
        ]);
    }

    private function photo(IpcBatch $batch, string $field, ?string $name = null): IpcAttachment
    {
        $path = "ipc-attachments/{$batch->id}/startup/".($name ?? $field).'.jpg';
        Storage::disk('public')->put($path, "jpeg-bytes-{$field}");

        return IpcAttachment::create([
            'ipc_batch_id' => $batch->id, 'stage' => 'startup', 'field_label' => $field,
            'file_path' => $path, 'uploaded_by' => auth()->id(),
        ]);
    }

    /** A coding_formula response; $fields override batch / exp entries. */
    private function formula(array $fields = [], array $overrides = []): array
    {
        return [
            'status' => 'OK',
            'formula_source' => 'auto',
            'formula' => ['{BATCH3}', 'EXP {DD}{MM}{YY}'],
            'required_fields' => ['batch', 'exp'],
            'fields' => [
                'batch' => ['status' => 'OK', 'value' => 'EKB', 'photos' => [0], 'required' => true],
                'exp' => [
                    'status' => 'OK', 'value' => '231029', 'date' => ['year' => 2029, 'month' => 10, 'day' => 23],
                    'granularity' => 'day', 'photos' => [0], 'required' => true,
                ],
                ...$fields,
            ],
            'photos' => [],
            'confidence' => 0.95, 'engine_used' => 'ollama', 'model' => 'qwen3.8:27b', 'attempts' => 3, 'elapsed_sec' => 4.1,
            'field_type' => 'coding_formula', 'pipeline' => 'llm',
            ...$overrides,
        ];
    }

    private function fakeVision(array|\Closure $response, int $status = 200): void
    {
        Http::fake(['vision.test/api/analyze' => $response instanceof \Closure ? $response : Http::response($response, $status)]);
    }

    private function queue(IpcBatch $batch, array $form = ['packaging_type' => 'non_tube', 'exp_date' => '2029-10-23'])
    {
        return $this->postJson(route('startup-check.ai-queue', $batch), $form)->assertOk();
    }

    /** Value of a multipart part sent to the Vision Service. */
    private function part(Request $request, string $name): ?string
    {
        foreach ($request->data() as $part) {
            if (($part['name'] ?? null) === $name) {
                return (string) $part['contents'];
            }
        }

        return null;
    }

    public function test_matching_non_tube_read_is_pass_and_logged_with_formula_source(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula());

        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.field', 'coding_primary')
            ->assertJsonPath('fields.0.state', 'done')
            ->assertJsonPath('fields.0.decision', 'PASS');
        $items = collect($response->json('fields.0.items'))->keyBy('key');
        $this->assertSame('match', $items['coding_batch']['verdict']);
        $this->assertSame('match', $items['coding_exp']['verdict']);
        $this->assertSame('23/10/2029', $items['coding_exp']['expected']);
        $this->assertSame('231029', $items['coding_exp']['read']); // value exactly as read
        $this->assertStringContainsString('foto 1', $items['coding_batch']['note']);
        $this->assertStringContainsString('otomatis', $items['formula']['note']);

        $log = IpcLog::where('field_type', 'coding_formula')->sole();
        $this->assertSame('REVIEW', $log->decision); // the read log itself never decides
        $this->assertSame('auto', $log->comparison['formula_source']);
        $this->assertSame('auto', $log->comparison['formula_sent']);
        $this->assertNull($log->comparison['image_path_2']);
        $this->assertFalse($log->comparison['batch_differs']);
        $this->assertFalse($log->comparison['exp_differs']);
        $this->assertSame('batch=EKB; exp=231029', $log->ocr_value);
    }

    public function test_month_granularity_compares_month_and_year_only(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula([
            'exp' => ['status' => 'OK', 'value' => 'EXP OCT 29', 'date' => ['year' => 2029, 'month' => 10], 'granularity' => 'month', 'photos' => [0]],
        ]));

        // The form EXP is the 23rd; the print has no day, so only 10/2029 is compared.
        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.decision', 'PASS');
        $exp = collect($response->json('fields.0.items'))->firstWhere('key', 'coding_exp');
        $this->assertSame('10/2029', $exp['expected']);
        $this->assertSame('EXP OCT 29', $exp['read']);
        $this->assertStringContainsString('bulan-tahun', $exp['note']);
    }

    public function test_trusted_different_values_are_fail(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula([
            'exp' => ['status' => 'OK', 'value' => '231129', 'date' => ['year' => 2029, 'month' => 11, 'day' => 23], 'granularity' => 'day', 'photos' => [0]],
        ]));

        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.decision', 'FAIL');
        $this->assertSame('mismatch', collect($response->json('fields.0.items'))->firstWhere('key', 'coding_exp')['verdict']);
        $this->assertTrue(IpcLog::where('field_type', 'coding_formula')->sole()->comparison['exp_differs']);
    }

    public function test_batch_mismatch_is_fail(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula(['batch' => ['status' => 'OK', 'value' => 'AKB', 'photos' => [0]]]));

        $this->queue($batch)->assertJsonPath('fields.0.decision', 'FAIL');
    }

    public function test_conflict_between_photos_is_review_with_both_values_shown(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->photo($batch, 'coding_primary_2');
        $this->fakeVision($this->formula(['batch' => [
            'status' => 'CONFLICT', 'value' => null,
            'per_photo' => [['photo' => 0, 'status' => 'OK', 'value' => 'OKB'], ['photo' => 1, 'status' => 'OK', 'value' => 'AKB']],
        ]], ['status' => 'LOW_CONFIDENCE']));

        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.decision', 'REVIEW');
        $batchItem = collect($response->json('fields.0.items'))->firstWhere('key', 'coding_batch');
        $this->assertSame('review', $batchItem['verdict']);
        $this->assertSame('foto 1: OKB / foto 2: AKB', $batchItem['read']);
        $this->assertStringContainsString('Foto 1 dan foto 2 terbaca beda', $batchItem['note']);
    }

    public function test_low_confidence_field_is_review_never_pass_or_fail(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        // Even a value that happens to differ is not a FAIL while the read is not trusted.
        $this->fakeVision($this->formula([
            'exp' => ['status' => 'LOW_CONFIDENCE', 'value' => '231129', 'date' => ['year' => 2029, 'month' => 11, 'day' => 23], 'granularity' => 'day', 'photos' => []],
        ], ['status' => 'LOW_CONFIDENCE']));

        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.decision', 'REVIEW');
        $this->assertSame('review', collect($response->json('fields.0.items'))->firstWhere('key', 'coding_exp')['verdict']);
    }

    public function test_missing_required_field_is_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $response = $this->formula();
        unset($response['fields']['exp']);
        $this->fakeVision($response);

        $this->queue($batch)->assertJsonPath('fields.0.decision', 'REVIEW');
    }

    public function test_llm_unavailable_error_is_review_and_does_not_crash(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(['status' => 'ERROR', 'error' => 'LLM_UNAVAILABLE: connection refused', 'confidence' => 0.0, 'engine_used' => 'ollama', 'field_type' => 'coding_formula']);

        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.decision', 'REVIEW');
        $this->assertStringContainsString('LLM_UNAVAILABLE', $response->json('fields.0.reason'));
        $this->assertSame('LLM_UNAVAILABLE: connection refused', IpcLog::where('field_type', 'coding_formula')->sole()->error_reason);
    }

    public function test_invalid_formula_http_400_is_review_and_does_not_crash(): void
    {
        $batch = $this->makeBatch('{NOPE}');
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(['status' => 'ERROR', 'error' => 'FORMULA_INVALID: token {NOPE} tidak dikenal'], 400);

        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.decision', 'REVIEW');
        $this->assertStringContainsString('FORMULA_INVALID', IpcLog::where('field_type', 'coding_formula')->sole()->error_reason);
    }

    public function test_second_photo_is_sent_as_file2_in_the_same_single_call(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $second = $this->photo($batch, 'coding_primary_2');
        $this->fakeVision($this->formula());

        $response = $this->queue($batch, ['packaging_type' => 'non_tube', 'exp_date' => '2029-10-23']);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $this->part($r, 'field_type') === 'coding_formula'
            && $this->part($r, 'file') === 'jpeg-bytes-coding_primary'
            && $this->part($r, 'file2') === 'jpeg-bytes-coding_primary_2');
        // One row for the pair; photo 2 has no read of its own.
        $this->assertCount(1, $response->json('fields'));
        $this->assertStringContainsString('foto 2', $response->json('fields.0.label'));
        $this->assertSame($second->file_path, IpcLog::sole()->comparison['image_path_2']);
    }

    public function test_a_second_photo_added_later_reads_the_pair_again(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula());

        $this->queue($batch);
        $this->queue($batch); // idempotent: nothing new to read
        Http::assertSentCount(1);

        $this->photo($batch, 'coding_primary_2');
        $this->queue($batch);

        Http::assertSentCount(2);
        $this->assertSame(2, IpcLog::where('field_type', 'coding_formula')->count());
        Http::assertSent(fn (Request $r) => $this->part($r, 'file2') !== null);
    }

    public function test_product_without_formula_sends_auto_and_a_product_formula_is_sent_as_is(): void
    {
        $auto = $this->makeBatch();
        $this->photo($auto, 'coding_primary');
        $sku = $this->makeBatch('["{BATCH3}","EXP {MMM} {YY}"]');
        $this->photo($sku, 'coding_primary');
        $this->fakeVision($this->formula());

        $this->queue($auto);
        $this->queue($sku);

        Http::assertSent(fn (Request $r) => $this->part($r, 'coding_formula') === 'auto');
        Http::assertSent(fn (Request $r) => $this->part($r, 'coding_formula') === '["{BATCH3}","EXP {MMM} {YY}"]');
    }

    public function test_old_tube_path_is_not_called_while_coding_formula_is_active(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->photo($batch, 'coding_primary_2');
        $this->fakeVision($this->formula());

        $this->queue($batch, ['packaging_type' => 'tube', 'exp_date' => '2029-10-23']);

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => in_array($this->part($r, 'field_type'), ['coding_print', 'tube_exp_date', 'tube_exp_llm'], true));
        $this->assertSame(['coding_formula'], IpcLog::pluck('field_type')->all());
    }

    public function test_old_tube_reads_can_be_switched_back_on_for_comparison(): void
    {
        config(['vision.startup_ai.legacy_tube_reads' => true]);
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision(fn (Request $r) => Http::response($this->part($r, 'field_type') === 'coding_formula'
            ? $this->formula()
            : ['status' => 'LOW_CONFIDENCE', 'confidence' => 0.3]));

        $this->queue($batch, ['packaging_type' => 'tube', 'exp_date' => '2029-10-23']);

        $this->assertEqualsCanonicalizing(['coding_formula', 'tube_exp_date', 'tube_exp_llm'], IpcLog::pluck('field_type')->all());
    }

    public function test_tube_stays_review_by_default_and_follows_non_tube_rules_when_the_policy_is_off(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula());
        $tube = ['packaging_type' => 'tube', 'exp_date' => '2029-10-23'];

        $this->queue($batch, $tube)->assertJsonPath('fields.0.decision', 'REVIEW');

        config(['vision.startup_ai.tube_force_review' => false]);
        $this->getJson(route('startup-check.ai-status', ['batch' => $batch, ...$tube]))->assertOk()
            ->assertJsonPath('fields.0.decision', 'PASS');
    }

    public function test_packaging_type_not_chosen_is_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula());

        $this->queue($batch, ['exp_date' => '2029-10-23'])->assertJsonPath('fields.0.decision', 'REVIEW');
    }

    public function test_batch_printed_with_another_length_is_shown_and_review(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula(['batch' => ['status' => 'OK', 'value' => '26Y070X', 'photos' => [0]]]));

        $response = $this->queue($batch);

        $response->assertJsonPath('fields.0.decision', 'REVIEW');
        $item = collect($response->json('fields.0.items'))->firstWhere('key', 'coding_batch');
        $this->assertSame('26Y070X', $item['read']);
        $this->assertSame('review', $item['verdict']);
    }

    public function test_feedback_pairs_the_formula_read_with_the_saved_values(): void
    {
        $batch = $this->makeBatch();
        $this->photo($batch, 'coding_primary');
        $this->fakeVision($this->formula([
            'exp' => ['status' => 'OK', 'value' => 'EXP OCT 29', 'date' => ['year' => 2029, 'month' => 10], 'granularity' => 'month', 'photos' => [0]],
        ]));
        $this->queue($batch);

        app(RecordStartupAiFeedback::class)->handle($batch);

        $rows = IpcAiFieldFeedback::where('ipc_batch_id', $batch->id)->get()->keyBy('field_key');
        $this->assertSame('coding_formula', $rows['coding_batch']->field_type);
        $this->assertSame('match', $rows['coding_batch']->outcome);
        $this->assertSame('2029-10', $rows['coding_exp']->ai_value);
        $this->assertSame('2029-10', $rows['coding_exp']->final_value);
        $this->assertSame('match', $rows['coding_exp']->outcome);
    }

    public function test_master_product_form_saves_a_formula_and_blank_means_auto(): void
    {
        $this->withoutMiddleware();
        $payload = ['fg_code' => 'FG-NEW', 'product_name' => 'Baru', 'is_active' => true];

        $this->post(route('master-products.store'), [...$payload, 'coding_formula' => ' ["{BATCH3}","EXP {MMM} {YY}"] '])->assertSessionHasNoErrors();
        $product = MasterProduct::where('fg_code', 'FG-NEW')->sole();
        $this->assertSame('["{BATCH3}","EXP {MMM} {YY}"]', $product->coding_formula);

        $this->post(route('master-products.store'), [...$payload, 'id' => $product->id, 'coding_formula' => 'auto'])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->coding_formula);
        $this->assertSame('auto', $product->fresh()->codingFormulaForVision());

        $this->post(route('master-products.store'), [...$payload, 'id' => $product->id, 'coding_formula' => '["{BATCH3}", 5]'])
            ->assertSessionHasErrors('coding_formula');
    }
}
