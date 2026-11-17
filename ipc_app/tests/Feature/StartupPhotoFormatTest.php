<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\User;
use App\Services\Vision\StartupEvaluation;
use App\Services\Vision\StartupVisionEvaluator;
use App\Services\Vision\VisionEvaluation;
use App\Services\Vision\VisionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Format foto" advice for Label Bulk and IM photos: tells the operator when the photo is not the
 * expected document / is cut off. Advice only (INFO + retake banner), it never changes the PASS/REVIEW/FAIL decision. The two real
 * production-style responses below come from the sample photos checked with the Vision Service.
 */
class StartupPhotoFormatTest extends TestCase
{
    use RefreshDatabase;

    private function batch(): IpcBatch
    {
        $user = User::factory()->create();
        $product = MasterProduct::updateOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => '26X035/KKB',
            'created_by' => $user->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);
    }

    private function evaluate(string $type, array $raw): StartupEvaluation
    {
        return app(StartupVisionEvaluator::class)->evaluate(
            $this->batch(),
            $type,
            VisionResult::fromResponse(['status' => 'OK', 'pipeline' => 'llm', 'confidence' => 0.9, ...$raw]),
            [],
        );
    }

    private function formatItem(StartupEvaluation $e): array
    {
        return collect($e->items)->firstWhere('key', 'photo_format');
    }

    private function labelBulk(array $overrides = []): array
    {
        $ok = ['no_batch' => true, 'mixing_date' => true, 'kode_bulk' => true];

        return [
            'no_batch_raw' => '26X035/KKB', 'mixing_date_raw' => '26/10/2026', 'kode_bulk' => '6587010023',
            'legible' => $ok, 'format_ok' => $ok, 'view_agreement' => $ok,
            ...$overrides,
        ];
    }

    public function test_complete_label_bulk_photo_is_format_ok(): void
    {
        $item = $this->formatItem($this->evaluate('label_bulk', $this->labelBulk()));

        $this->assertSame('info', $item['verdict']);
        $this->assertFalse($item['retake']);
    }

    public function test_batch_number_variants_seen_in_real_data_are_accepted(): void
    {
        foreach (['26X035/KKB', '26X035 KKB', 'KKB 26X035', '26X035'] as $value) {
            $item = $this->formatItem($this->evaluate('label_bulk', $this->labelBulk(['no_batch_raw' => $value])));
            $this->assertSame('info', $item['verdict'], $value);
        }
    }

    public function test_incomplete_label_bulk_photo_gets_a_specific_retake_advice_but_never_fails(): void
    {
        $result = $this->evaluate('label_bulk', $this->labelBulk(['kode_bulk' => '658701002', 'mixing_date_raw' => null, 'no_batch_raw' => 'ABC-12']));
        $item = $this->formatItem($result);

        $this->assertSame('info', $item['verdict']); // advice only, never decides
        $this->assertTrue($item['retake']);
        $this->assertStringContainsString('Kode Bulk', $item['note']);
        $this->assertStringContainsString('Mixing Date tidak terbaca', $item['note']);
        $this->assertStringContainsString('No. Batch', $item['note']);
    }

    public function test_cut_off_im_photo_from_the_field_is_flagged(): void
    {
        // Real response for the cropped IM page: IM number and shelf-life disagree between views.
        $raw = [
            'im_number' => 'IM-ABL- SCULPT CALM LILY DEODORANT 25ML',
            'shelf_life' => [], 'shelf_life_value' => null,
            'legible' => ['im_number' => true, 'shelf_life' => false],
            'format_ok' => ['im_number' => true, 'shelf_life' => false],
            'view_agreement' => ['im_number' => false, 'shelf_life' => false],
        ];
        $result = $this->evaluate('im_document', $raw);
        $item = $this->formatItem($result);

        $this->assertSame('info', $item['verdict']); // advice only, never decides
        $this->assertTrue($item['retake']);
        $this->assertStringContainsString('No. IM terbaca berbeda-beda', $item['note']);
        $this->assertStringContainsString('tidak konsisten antar sudut', $item['note']);
        $this->assertNotSame(VisionEvaluation::FAIL, $result->decision);
    }

    public function test_clear_im_photo_is_format_ok(): void
    {
        $ok = ['im_number' => true, 'shelf_life' => true];
        $item = $this->formatItem($this->evaluate('im_document', [
            'im_number' => 'IM-ABL- SCULPT CALM LILY DEODORANT 25ML V2',
            'shelf_life_value' => ['years' => 2, 'months' => null],
            'legible' => $ok, 'format_ok' => $ok, 'view_agreement' => $ok,
        ]));

        $this->assertSame('info', $item['verdict']);
    }

    public function test_im_photo_that_is_not_an_im_or_has_absurd_shelf_life_is_flagged(): void
    {
        $ok = ['im_number' => true, 'shelf_life' => true];
        $notIm = $this->formatItem($this->evaluate('im_document', [
            'im_number' => 'PACKING INSTRUCTION', 'shelf_life_value' => ['years' => 2, 'months' => null],
            'legible' => $ok, 'format_ok' => $ok, 'view_agreement' => $ok,
        ]));
        $absurd = $this->formatItem($this->evaluate('im_document', [
            'im_number' => 'IM-ABL- X', 'shelf_life_value' => ['years' => 0, 'months' => 2],
            'legible' => $ok, 'format_ok' => $ok, 'view_agreement' => $ok,
        ]));

        $this->assertStringContainsString('tidak berawalan IM-XXX', $notIm['note']);
        $this->assertStringContainsString('di luar kewajaran', $absurd['note']);
    }

    public function test_format_advice_never_changes_the_decision(): void
    {
        $ok = ['im_number' => true, 'shelf_life' => true];
        $batch = $this->batch();
        $batch->update(['shelf_life_months' => 36, 'mixing_date' => '2026-10-23', 'exp_date' => '2029-10-23']);

        // A trusted, correct shelf-life on an odd-looking IM number: still PASS, plus the advice banner.
        $result = app(StartupVisionEvaluator::class)->evaluate(
            $batch,
            'im_document',
            VisionResult::fromResponse([
                'status' => 'OK', 'pipeline' => 'llm', 'confidence' => 0.9, 'im_number' => 'IM-123',
                'shelf_life_value' => ['years' => 3, 'months' => null],
                'legible' => $ok, 'format_ok' => $ok, 'view_agreement' => $ok,
            ]),
            ['mixing_date' => '2026-10-23', 'exp_date' => '2029-10-23', 'shelf_life_months' => 36],
        );

        $this->assertSame(VisionEvaluation::PASS, $result->decision);
        $this->assertTrue($this->formatItem($result)['retake']);
    }
}
