<?php

namespace Tests\Feature;

use App\Actions\Vision\QueueStartupAiReads;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Scan Label Bulk on Batch Baru: AI read of the photo + the photo becoming the Startup Label Bulk photo. */
class BatchLabelScanTest extends TestCase
{
    use RefreshDatabase;

    private MasterProduct $product;

    private MasterProductBulkCode $bulk;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['vision.base_url' => 'http://vision.test', 'vision.startup_ai.enabled' => true]);
        $this->actingAs(User::factory()->create());

        $this->product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'shelf_life_months' => 36, 'is_active' => true]);
        $this->bulk = MasterProductBulkCode::create(['master_product_id' => $this->product->id, 'bulk_code' => '6124490033', 'is_active' => true]);
    }

    private function label(array $overrides = []): array
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

    private function scan(array $visionResponse)
    {
        Http::fake(['vision.test/api/analyze' => Http::response($visionResponse)]);

        return $this->postJson('/batches/label-scan', ['photo' => UploadedFile::fake()->image('label.jpg', 800, 1000)]);
    }

    public function test_trusted_read_returns_batch_mixing_date_and_the_fg_owning_the_bulk_code(): void
    {
        $this->scan($this->label())
            ->assertOk()
            ->assertJsonPath('read.no_batch', '26X005/EKB')
            ->assertJsonPath('read.no_batch_trusted', true)
            ->assertJsonPath('read.mixing_date', '2026-10-23')
            ->assertJsonPath('read.kode_bulk', '6124490033')
            ->assertJsonPath('read.product.id', $this->product->id)
            ->assertJsonPath('read.product.bulk_codes.0.id', $this->bulk->id);

        Http::assertSent(fn ($r) => str_contains($r->body(), 'label_bulk'));
    }

    public function test_batch_number_written_with_a_space_or_in_reverse_order_is_normalised(): void
    {
        $this->scan($this->label(['no_batch_raw' => '26X005 EKB']))->assertJsonPath('read.no_batch', '26X005/EKB');
        $this->scan($this->label(['no_batch_raw' => 'EKB 26X005']))->assertJsonPath('read.no_batch', '26X005/EKB');
    }

    public function test_untrusted_or_malformed_values_are_not_marked_trusted_and_never_guessed(): void
    {
        $this->scan($this->label([
            'no_batch_raw' => '26X05/EKB',
            'view_agreement' => ['no_batch' => false, 'mixing_date' => true, 'kode_bulk' => false],
        ]))
            ->assertOk()
            ->assertJsonPath('read.no_batch', null)
            ->assertJsonPath('read.no_batch_raw', '26X05/EKB')
            ->assertJsonPath('read.kode_bulk_trusted', false);
    }

    public function test_unknown_bulk_code_gives_no_product(): void
    {
        $this->scan($this->label(['kode_bulk' => '9999999999']))->assertJsonPath('read.product', null);
    }

    public function test_service_failure_still_parks_the_photo_and_reports_no_read(): void
    {
        Http::fake(['vision.test/api/analyze' => Http::response('boom', 500)]);

        $response = $this->postJson('/batches/label-scan', ['photo' => UploadedFile::fake()->image('label.jpg')])->assertOk();

        $response->assertJsonPath('read.vision_status', 'ERROR')->assertJsonPath('read.no_batch', null);
        Storage::disk('public')->assertExists('ipc-attachments/pending/'.auth()->id().'/'.$response->json('scan_id').'.jpg');
    }

    public function test_with_ai_disabled_the_photo_is_kept_without_calling_the_service(): void
    {
        config(['vision.startup_ai.enabled' => false]);
        Http::fake();

        $this->postJson('/batches/label-scan', ['photo' => UploadedFile::fake()->image('label.jpg')])
            ->assertOk()
            ->assertJsonPath('ai_enabled', false)
            ->assertJsonPath('read', null);

        Http::assertNothingSent();
    }

    public function test_non_image_is_rejected(): void
    {
        $this->postJson('/batches/label-scan', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);
    }

    public function test_creating_the_batch_attaches_the_photo_and_its_read_to_startup_check(): void
    {
        $scanId = $this->scan($this->label())->json('scan_id');

        $this->post('/batches', [
            'master_product_id' => $this->product->id,
            'master_product_bulk_code_ids' => [$this->bulk->id],
            'no_batch' => '26X005/EKB',
            'shelf_life_months' => 36,
            'flow_type' => IpcBatch::FLOW_FULL,
            'label_scan_id' => $scanId,
        ])->assertRedirect();

        $batch = IpcBatch::firstOrFail();
        $attachment = IpcAttachment::where('ipc_batch_id', $batch->id)->where('stage', 'startup')->where('field_label', 'date_bulk')->firstOrFail();
        Storage::disk('public')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing('ipc-attachments/pending/'.auth()->id().'/'.$scanId.'.jpg');

        // The read is logged against the SAVED photo, so Startup's "Rekomendasi AI" finds it and does not re-read.
        $log = IpcLog::where('ipc_batch_id', $batch->id)->where('field_type', 'label_bulk')->firstOrFail();
        $this->assertSame($attachment->file_path, $log->image_path);
        $this->assertTrue($log->comparison['from_batch_create']);
        $this->assertSame('26X005/EKB', $log->comparison['entered']['no_batch']);
        $this->assertSame('26X005/EKB', $log->comparison['ai']['no_batch']);
        $this->assertNotNull(QueueStartupAiReads::findLog($batch, $attachment, 'label_bulk'));

        // Startup Check's required-photo rule is satisfied without a second photo.
        $this->actingAs(auth()->user())->get("/batches/{$batch->id}/startup-check")->assertOk()
            ->assertInertia(fn ($page) => $page->whereNot('photoUrls.date_bulk', null));
    }

    public function test_batch_without_a_scan_is_created_as_before(): void
    {
        $this->post('/batches', [
            'master_product_id' => $this->product->id,
            'master_product_bulk_code_ids' => [$this->bulk->id],
            'no_batch' => '26X005/EKB',
            'shelf_life_months' => 36,
            'flow_type' => IpcBatch::FLOW_FULL,
        ])->assertRedirect();

        $this->assertSame(0, IpcAttachment::count());
        $this->assertSame(0, IpcLog::count());
    }

    public function test_someone_elses_scan_cannot_be_attached(): void
    {
        $scanId = $this->scan($this->label())->json('scan_id');

        $this->actingAs(User::factory()->create())->post('/batches', [
            'master_product_id' => $this->product->id,
            'master_product_bulk_code_ids' => [$this->bulk->id],
            'no_batch' => '26X005/EKB',
            'shelf_life_months' => 36,
            'flow_type' => IpcBatch::FLOW_FULL,
            'label_scan_id' => $scanId,
        ])->assertRedirect();

        $this->assertSame(0, IpcAttachment::count());
    }

    public function test_discarding_removes_the_parked_photo(): void
    {
        $scanId = $this->scan($this->label())->json('scan_id');
        $path = 'ipc-attachments/pending/'.auth()->id()."/{$scanId}.jpg";
        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/batches/label-scan/{$scanId}")->assertOk();

        Storage::disk('public')->assertMissing($path);
    }
}
