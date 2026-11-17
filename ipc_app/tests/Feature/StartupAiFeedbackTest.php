<?php

namespace Tests\Feature;

use App\Actions\Vision\RecordStartupAiFeedback;
use App\Models\IpcAiFieldFeedback;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/** AI accuracy measurement: AI read vs the value finally saved on the Startup Check. */
class StartupAiFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function batch(): IpcBatch
    {
        $user = User::factory()->create();
        $product = MasterProduct::updateOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => '26X005/EKB',
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
            'mixing_date' => '2026-10-23',
            'exp_date' => '2029-10-23',
            'shelf_life_months' => 36,
        ]);
    }

    private function log(IpcBatch $batch, string $type, array $raw, string $status = 'OK'): IpcLog
    {
        return IpcLog::create([
            'request_id' => (string) Str::uuid(),
            'ipc_batch_id' => $batch->id,
            'stage' => IpcBatch::STAGE_STARTUP,
            'field_type' => $type,
            'image_path' => "ipc-attachments/{$batch->id}/startup/{$type}.jpg",
            'vision_status' => $status,
            'vision_response' => $raw,
            'decision' => 'REVIEW',
            'decision_reason' => 'test',
            'created_by' => $batch->created_by,
        ]);
    }

    private function trusted(string ...$keys): array
    {
        $flags = array_fill_keys($keys, true);

        return ['legible' => $flags, 'format_ok' => $flags, 'view_agreement' => $flags];
    }

    public function test_pairs_ai_reads_with_saved_values(): void
    {
        $batch = $this->batch();
        $this->log($batch, 'label_bulk', ['mixing_date_raw' => '23/10/2026', 'no_batch_raw' => '26X005 EKB'] + $this->trusted('mixing_date', 'no_batch'));
        $this->log($batch, 'im_document', ['shelf_life_value' => ['years' => 3, 'months' => 0]] + $this->trusted('shelf_life'));

        $written = app(RecordStartupAiFeedback::class)->handle($batch);

        $this->assertSame(3, $written);
        $mixing = IpcAiFieldFeedback::where('field_key', 'mixing_date')->firstOrFail();
        $this->assertSame('2026-10-23', $mixing->ai_value);
        $this->assertSame('match', $mixing->outcome);
        $this->assertTrue($mixing->ai_trusted);
        $this->assertSame('typed', $mixing->source);
        $this->assertSame('match', IpcAiFieldFeedback::where('field_key', 'no_batch')->value('outcome'));
        $this->assertSame('36', IpcAiFieldFeedback::where('field_key', 'shelf_life')->value('ai_value'));
    }

    public function test_copied_suggestion_is_labelled_and_not_counted_as_typed(): void
    {
        $batch = $this->batch();
        $this->log($batch, 'label_bulk', ['mixing_date_raw' => '23/10/2026'] + $this->trusted('mixing_date'));

        app(RecordStartupAiFeedback::class)->handle($batch, ['mixing_date']);

        $this->assertSame('suggestion_applied', IpcAiFieldFeedback::where('field_key', 'mixing_date')->value('source'));
    }

    public function test_suggestion_edited_afterwards_is_labelled_and_is_a_mismatch(): void
    {
        $batch = $this->batch();
        $this->log($batch, 'label_bulk', ['mixing_date_raw' => '22/10/2026'] + $this->trusted('mixing_date'));

        app(RecordStartupAiFeedback::class)->handle($batch, ['mixing_date']);

        $row = IpcAiFieldFeedback::where('field_key', 'mixing_date')->firstOrFail();
        $this->assertSame('edited_after_suggestion', $row->source);
        $this->assertSame('mismatch', $row->outcome);
    }

    public function test_trusted_but_wrong_and_unreadable_are_recorded(): void
    {
        $batch = $this->batch();
        $this->log($batch, 'label_bulk', ['mixing_date_raw' => '24/10/2026', 'no_batch_raw' => null] + $this->trusted('mixing_date'));

        app(RecordStartupAiFeedback::class)->handle($batch);

        $wrong = IpcAiFieldFeedback::where('field_key', 'mixing_date')->firstOrFail();
        $this->assertTrue($wrong->ai_trusted);
        $this->assertSame('mismatch', $wrong->outcome);
        $this->assertSame('ai_unreadable', IpcAiFieldFeedback::where('field_key', 'no_batch')->value('outcome'));
    }

    public function test_re_recording_updates_instead_of_duplicating(): void
    {
        $batch = $this->batch();
        $this->log($batch, 'label_bulk', ['mixing_date_raw' => '23/10/2026'] + $this->trusted('mixing_date'));

        app(RecordStartupAiFeedback::class)->handle($batch);
        app(RecordStartupAiFeedback::class)->handle($batch);

        $this->assertSame(1, IpcAiFieldFeedback::where('field_key', 'mixing_date')->count());
    }

    public function test_accuracy_command_excludes_copied_suggestions_from_ground_truth(): void
    {
        $batch = $this->batch();
        $this->log($batch, 'label_bulk', ['mixing_date_raw' => '23/10/2026'] + $this->trusted('mixing_date'));
        app(RecordStartupAiFeedback::class)->handle($batch, ['mixing_date']);

        Artisan::call('vision:accuracy', ['--json' => true]);
        $rows = json_decode(Artisan::output(), true);

        $this->assertSame(0, $rows[0]['ground_truth']);
        $this->assertSame(1, $rows[0]['suggestion_used']);
        $this->assertSame(0, $rows[0]['trusted_correct']);
    }
}
