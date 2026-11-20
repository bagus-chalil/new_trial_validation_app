<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevision;
use App\Models\StartupCheck;
use App\Models\StartupInspection;
use App\Models\StartupInspectionItem;
use App\Models\User;
use App\Services\Insights\NotOkPatternAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotOkPatternTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MasterLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->approver()->create();
        $this->line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        Cache::flush();
    }

    private function product(string $code = 'FG-1'): MasterProduct
    {
        return MasterProduct::firstOrCreate(['fg_code' => $code], ['product_name' => "Product {$code}", 'is_active' => true]);
    }

    /**
     * @param  array<string, string>  $packing  column => status, saved as one Packing revision
     * @param  array<string, string>  $startup  column => status on the live Startup Check
     */
    private function batch(string $noBatch, array $packing = [], array $startup = [], ?MasterProduct $product = null, ?MasterLine $line = null): IpcBatch
    {
        $batch = IpcBatch::create([
            'master_product_id' => ($product ?? $this->product())->id,
            'no_batch' => $noBatch,
            'master_line_id' => ($line ?? $this->line)->id,
            'created_by' => $this->user->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->user->id, 'completed_at' => now()] + $startup);

        $check = PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->user->id, 'save_count' => 1, 'completed_at' => now()]);
        PackingCheckRevision::create(['packing_check_id' => $check->id, 'revision_no' => 1, 'finalize' => true, 'user_id' => $this->user->id] + $packing);

        return $batch->fresh();
    }

    public function test_a_clean_batch_has_no_findings(): void
    {
        $batch = $this->batch('B-CLEAN', ['primary_bulk_status' => 'Conform', 'secondary_identity_status' => 'N/A']);

        $this->assertSame([], app(NotOkPatternAnalyzer::class)->forBatch($batch));
    }

    public function test_findings_come_from_startup_inspection_and_packing_and_ignore_na_and_partial_ok(): void
    {
        $batch = $this->batch('B-1', ['primary_bulk_status' => 'Not Conform', 'primary_attribute_status' => 'N/A'], ['product_standard_status' => 'Not Available', 'wi_im_match_status' => 'Available']);

        $inspection = StartupInspection::create(['ipc_batch_id' => $batch->id, 'user_id' => $this->user->id, 'completed_at' => now()]);
        StartupInspectionItem::create(['startup_inspection_id' => $inspection->id, 'parameter_key' => 'bulk_odor', 'status' => 'Not OK']);
        StartupInspectionItem::create(['startup_inspection_id' => $inspection->id, 'parameter_key' => 'primer', 'status' => 'Partial OK']);

        $keys = array_column(app(NotOkPatternAnalyzer::class)->forBatch($batch), 'key');
        sort($keys);

        $this->assertSame(['inspection.bulk_odor', 'packing.primary_bulk_status', 'startup.product_standard_status'], $keys);
    }

    public function test_an_item_repeated_in_every_round_still_counts_once_per_batch(): void
    {
        $batch = $this->batch('B-ROUNDS', ['primary_bulk_status' => 'Not Conform']);
        $check = $batch->packingCheck;
        PackingCheckRevision::create(['packing_check_id' => $check->id, 'revision_no' => 2, 'finalize' => true, 'user_id' => $this->user->id, 'primary_bulk_status' => 'Not Conform']);

        $earlier = $this->batch('B-PREV', ['primary_bulk_status' => 'Not Conform']);
        PackingCheckRevision::create(['packing_check_id' => $earlier->packingCheck->id, 'revision_no' => 2, 'finalize' => true, 'user_id' => $this->user->id, 'primary_bulk_status' => 'Not Conform']);

        $row = app(NotOkPatternAnalyzer::class)->forBatch($batch)[0];

        // 1 earlier batch with the finding (not 2 rounds), and one hit is not yet a pattern.
        $this->assertSame(['hits' => 1, 'of' => 1], $row['product']);
        $this->assertFalse($row['repeating']);
    }

    public function test_a_finding_that_came_back_on_the_same_product_is_flagged_as_repeating(): void
    {
        foreach (['B-OLD-1', 'B-OLD-2', 'B-OLD-3'] as $i => $no) {
            $this->batch($no, $i < 2 ? ['primary_bulk_status' => 'Not Conform'] : ['primary_bulk_status' => 'Conform']);
        }
        $current = $this->batch('B-NOW', ['primary_bulk_status' => 'Not Conform']);

        $row = app(NotOkPatternAnalyzer::class)->forBatch($current)[0];

        $this->assertSame('Packing · Primary Bulk', $row['label']);
        $this->assertTrue($row['repeating']);
        $this->assertSame(['hits' => 2, 'of' => 3], $row['product']);
        $this->assertSame(['hits' => 2, 'of' => 3], $row['line']);
    }

    public function test_other_products_and_other_lines_are_not_mixed_in(): void
    {
        $otherProduct = $this->product('FG-2');
        $otherLine = MasterLine::create(['category' => 'Packing', 'area' => 'Skin Care', 'code' => 'SC 01', 'name' => 'Skin Care 01', 'is_active' => true]);

        $this->batch('B-OTHER-PRODUCT', ['primary_bulk_status' => 'Not Conform'], [], $otherProduct, $this->line);
        $this->batch('B-OTHER-LINE', ['primary_bulk_status' => 'Not Conform'], [], $this->product(), $otherLine);
        $current = $this->batch('B-NOW', ['primary_bulk_status' => 'Not Conform']);

        $row = app(NotOkPatternAnalyzer::class)->forBatch($current)[0];

        $this->assertSame(['hits' => 1, 'of' => 1], $row['product']);
        $this->assertSame(['hits' => 1, 'of' => 1], $row['line']);
    }

    public function test_batches_that_never_had_the_stage_do_not_inflate_the_denominator(): void
    {
        $filling = IpcBatch::create([
            'master_product_id' => $this->product()->id,
            'no_batch' => 'B-FILLING-ONLY',
            'master_line_id' => $this->line->id,
            'created_by' => $this->user->id,
            'current_stage' => IpcBatch::STAGE_APPROVAL,
            'flow_type' => IpcBatch::FLOW_FILLING,
        ]);
        $this->assertNotNull($filling->id);
        $this->batch('B-PREV', ['primary_bulk_status' => 'Not Conform']);
        $current = $this->batch('B-NOW', ['primary_bulk_status' => 'Not Conform']);

        $row = app(NotOkPatternAnalyzer::class)->forBatch($current)[0];

        $this->assertSame(['hits' => 1, 'of' => 1], $row['product']);
    }

    public function test_overview_lists_only_items_seen_on_at_least_two_batches_with_the_worst_line(): void
    {
        $this->batch('B-1', ['primary_bulk_status' => 'Not Conform', 'primary_attribute_status' => 'Not Conform']);
        $this->batch('B-2', ['primary_bulk_status' => 'Not Conform']);

        $rows = app(NotOkPatternAnalyzer::class)->overview();

        $this->assertCount(1, $rows);
        $this->assertSame('Packing · Primary Bulk', $rows[0]['label']);
        $this->assertSame(2, $rows[0]['batches']);
        $this->assertSame(2, $rows[0]['of']);
        $this->assertSame(['name' => 'Make Up 01', 'count' => 2], $rows[0]['line']);
        $this->assertSame(['name' => 'Product FG-1', 'count' => 2], $rows[0]['product']);
    }

    public function test_dashboard_and_batch_page_expose_the_patterns(): void
    {
        $this->batch('B-1', ['primary_bulk_status' => 'Not Conform']);
        $current = $this->batch('B-2', ['primary_bulk_status' => 'Not Conform']);

        $this->actingAs($this->user)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('repeatFindings', 1)
            ->where('repeatFindings.0.batches', 2));

        $this->actingAs($this->user)->get("/batches/{$current->id}")->assertInertia(fn ($page) => $page
            ->component('batches/show')
            ->has('notOkPatterns', 1)
            ->where('notOkPatterns.0.repeating', false)
            ->where('notOkPatterns.0.product.hits', 1));
    }

    public function test_does_not_apply_machine_items_are_not_findings_but_real_gaps_are(): void
    {
        $batch = $this->batch('B-MACHINES', [], [
            // "Not Available" here means the line has no such machine (95% of real batches), not a defect.
            'machine_roller_status' => 'Not Available',
            'machine_load_cell_status' => 'Not Available',
            'sample_challenge_test_status' => 'Not Available',
            // These are rare and mean a real gap.
            'product_standard_status' => 'Not Available',
            'wi_im_match_status' => 'Not Available',
        ]);

        $keys = array_column(app(NotOkPatternAnalyzer::class)->forBatch($batch), 'key');
        sort($keys);

        $this->assertSame(['startup.product_standard_status', 'startup.wi_im_match_status'], $keys);
        $this->assertArrayNotHasKey('startup.machine_roller_status', NotOkPatternAnalyzer::catalog());
    }
}
