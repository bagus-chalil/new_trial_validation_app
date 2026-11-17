<?php

namespace Tests\Feature;

use App\Actions\Vision\ReadStartupPhoto;
use App\Actions\Vision\RecordStartupAiFeedback;
use App\Actions\Vision\StartupAiStatus;
use App\Models\IpcAiFieldFeedback;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\User;
use App\Services\Vision\PhotoQuality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Blank / almost black photos (failed camera capture) are never sent to the Vision Service. */
class StartupPhotoQualityTest extends TestCase
{
    use RefreshDatabase;

    private function png(callable $paint): string
    {
        $img = imagecreatetruecolor(240, 320);
        $paint($img);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function solid(int $r, int $g, int $b): string
    {
        return $this->png(fn ($img) => imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b)));
    }

    private function textured(): string
    {
        return $this->png(function ($img) {
            imagefill($img, 0, 0, imagecolorallocate($img, 235, 235, 235));
            $black = imagecolorallocate($img, 20, 20, 20);
            for ($y = 20; $y < 300; $y += 24) {
                imagefilledrectangle($img, 20, $y, 20 + (($y * 7) % 180), $y + 8, $black);
            }
        });
    }

    public function test_quality_check_flags_black_and_flat_colour_but_not_a_real_photo(): void
    {
        $quality = new PhotoQuality;

        $this->assertTrue($quality->assess($this->solid(0, 0, 0))['blank']);
        $this->assertTrue($quality->assess($this->solid(139, 0, 0))['blank']); // solid red capture failure
        $this->assertTrue($quality->assess($this->solid(10, 10, 10))['blank']);
        $this->assertFalse($quality->assess($this->textured())['blank']);
        $this->assertNull($quality->assess('not an image'));
    }

    private function batchWithPhoto(string $bytes): array
    {
        Storage::fake('public');
        config(['vision.base_url' => 'http://vision.test']);
        $user = User::factory()->create();
        $product = MasterProduct::updateOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id, 'no_batch' => '26X005/EKB', 'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_STARTUP, 'exp_date' => '2029-10-23',
        ]);
        $path = "ipc-attachments/{$batch->id}/startup/coding_primary.jpg";
        Storage::disk('public')->put($path, $bytes);
        $attachment = IpcAttachment::create([
            'ipc_batch_id' => $batch->id, 'stage' => 'startup', 'field_label' => 'coding_primary', 'file_path' => $path, 'uploaded_by' => $user->id,
        ]);

        return [$batch, $attachment, $user];
    }

    public function test_blank_photo_is_logged_as_low_without_calling_the_vision_service(): void
    {
        Http::fake();
        [$batch, $attachment, $user] = $this->batchWithPhoto($this->solid(0, 0, 0));

        $log = app(ReadStartupPhoto::class)->handle($batch, $attachment, 'coding_primary', 'coding_print', $user->id);

        Http::assertNothingSent();
        $this->assertSame('LOW_CONFIDENCE', $log->vision_status);
        $this->assertSame('PHOTO_BLANK', $log->error_reason);
        $this->assertSame(ReadStartupPhoto::PRECHECK_ENGINE, $log->engine_used);
        $this->assertTrue($log->vision_response['photo_quality']['blank']);
    }

    public function test_status_card_shows_a_specific_retake_advice_for_a_blank_photo(): void
    {
        Http::fake();
        [$batch, $attachment, $user] = $this->batchWithPhoto($this->solid(0, 0, 0));
        app(ReadStartupPhoto::class)->handle($batch, $attachment, 'coding_primary', 'coding_print', $user->id);
        $batch->update(['primary_packaging_type' => 'non_tube']);

        $status = app(StartupAiStatus::class)->handle($batch, ['packaging_type' => 'non_tube']);

        $field = collect($status['fields'])->firstWhere('field', 'coding_primary');
        $this->assertSame('done', $field['state']);
        $this->assertSame('REVIEW', $field['decision']);
        $item = collect($field['items'])->firstWhere('key', 'photo_quality');
        $this->assertTrue($item['retake']);
        $this->assertStringContainsString('kosong atau hampir hitam', $item['note']);
        $this->assertSame([], $field['suggestions']);
    }

    public function test_a_normal_photo_still_goes_to_the_vision_service(): void
    {
        Http::fake(['vision.test/api/analyze' => Http::response([
            'pipeline' => 'llm', 'status' => 'OK', 'batch_raw' => 'EKB', 'exp_raw' => '231029', 'exp_format' => 'DDMMYY',
            'exp_parts' => ['day' => '23', 'month' => '10', 'year' => '29'], 'packaging_type' => 'non_tube', 'confidence' => 0.95,
            'legible' => ['batch' => true, 'exp' => true], 'format_ok' => ['batch' => true, 'exp' => true],
            'view_agreement' => ['batch' => true, 'exp' => true], 'trusted' => ['batch' => true, 'exp' => true],
        ])]);
        [$batch, $attachment, $user] = $this->batchWithPhoto($this->textured());

        $log = app(ReadStartupPhoto::class)->handle($batch, $attachment, 'coding_primary', 'coding_print', $user->id);

        Http::assertSentCount(1);
        $this->assertSame('OK', $log->vision_status);
        $this->assertNotSame(ReadStartupPhoto::PRECHECK_ENGINE, $log->engine_used);
    }

    public function test_blank_photo_logs_are_not_counted_as_ai_accuracy_feedback(): void
    {
        Http::fake();
        [$batch, $attachment, $user] = $this->batchWithPhoto($this->solid(0, 0, 0));
        app(ReadStartupPhoto::class)->handle($batch, $attachment, 'coding_primary', 'coding_print', $user->id);

        app(RecordStartupAiFeedback::class)->handle($batch);

        $this->assertSame(0, IpcAiFieldFeedback::count());
    }
}
