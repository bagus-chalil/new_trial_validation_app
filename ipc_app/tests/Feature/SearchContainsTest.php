<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\User;
use App\Support\SearchTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Search matches a term anywhere (middle/end), in any word order, without scanning wide rows. */
class SearchContainsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MasterProduct $product;

    private function makeBatch(string $noBatch, ?string $stage = null): IpcBatch
    {
        $this->user ??= User::factory()->create();
        $this->product ??= MasterProduct::create(['fg_code' => '672904014', 'product_name' => 'MOL Moell Healthy Baby Shampoo', 'is_active' => true]);
        $line = MasterLine::query()->first() ?? MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $this->product->id,
            'no_batch' => $noBatch,
            'master_line_id' => $line->id,
            'created_by' => $this->user->id,
            ...($stage ? ['current_stage' => $stage] : []),
        ]);
    }

    public function test_batch_number_is_found_by_its_end_middle_or_start(): void
    {
        $batch = $this->makeBatch('26Y252/BLB');
        $this->makeBatch('26Y013/NLB');

        foreach (['BLB', 'blb', '252', 'Y252/B', '26Y252/BLB'] as $term) {
            $this->actingAs($this->user)->getJson('/lookup/batches?q='.urlencode($term))
                ->assertOk()
                ->assertJsonCount(1)
                ->assertJsonPath('0.id', $batch->id);
        }
    }

    public function test_words_may_come_in_any_order_and_all_must_match(): void
    {
        $batch = $this->makeBatch('26Y252/BLB');
        $this->makeBatch('26Y013/NLB');

        $this->actingAs($this->user)->getJson('/lookup/batches?q='.urlencode('blb 26y252'))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $batch->id);

        $this->actingAs($this->user)->getJson('/lookup/batches?q='.urlencode('blb 99999'))
            ->assertOk()->assertJsonCount(0);
    }

    public function test_product_name_words_match_in_any_order(): void
    {
        $batch = $this->makeBatch('26Y252/BLB');

        $this->actingAs($this->user)->getJson('/lookup/batches?q='.urlencode('shampoo moell'))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $batch->id);

        $this->actingAs($this->user)->getJson('/lookup/products?q='.urlencode('baby 9040'))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->product->id);
    }

    public function test_wildcards_and_blank_terms_are_literal_or_ignored(): void
    {
        $this->makeBatch('26Y252/BLB');

        // "%" and "_" are matched literally, not as LIKE wildcards.
        $this->actingAs($this->user)->getJson('/lookup/batches?q='.urlencode('%'))->assertOk()->assertJsonCount(0);
        $this->actingAs($this->user)->getJson('/lookup/batches?q='.urlencode('2_Y'))->assertOk()->assertJsonCount(0);
        // A term that is only separators has no usable word: nothing is searched, nothing breaks.
        $this->actingAs($this->user)->getJson('/lookup/batches?q='.urlencode(' / '))->assertOk();
    }

    public function test_batch_list_and_archive_search_by_the_end_of_the_number_and_keep_filters(): void
    {
        $this->makeBatch('26Y252/BLB', IpcBatch::STAGE_FILLING);
        $this->makeBatch('26Y999/BLB', IpcBatch::STAGE_STARTUP);
        $archived = $this->makeBatch('26Y111/BLB', IpcBatch::STAGE_COMPLETED);
        $archived->forceFill(['archived_at' => now(), 'archived_by' => $this->user->id])->save();

        $this->actingAs($this->user)->get('/batches?q=BLB')
            ->assertInertia(fn ($page) => $page->where('batches.total', 2));

        $this->actingAs($this->user)->get('/batches?q=BLB&stage='.IpcBatch::STAGE_FILLING)
            ->assertInertia(fn ($page) => $page->where('batches.total', 1));

        // The archive page is admin-only (manage-master).
        $this->actingAs(User::factory()->admin()->create())->get('/archive?q=BLB')
            ->assertInertia(fn ($page) => $page->where('batches.total', 1));
    }

    public function test_search_term_helper_tokenizes_and_matches_in_memory(): void
    {
        $this->assertSame(['26Y252', 'BLB'], SearchTerm::tokens('  26Y252/BLB '));
        $this->assertSame([], SearchTerm::tokens(' / '));
        $this->assertCount(SearchTerm::MAX_TOKENS, SearchTerm::tokens('a b c d e f g h'));
        $this->assertTrue(SearchTerm::matches('FG-1 Glowing Cushion TRIAL-9', 'cushion glow'));
        $this->assertFalse(SearchTerm::matches('FG-1 Glowing Cushion', 'cushion tint'));
    }
}
