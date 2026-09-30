<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_use_lookups(): void
    {
        $this->getJson('/lookup/products')->assertUnauthorized();
        $this->getJson('/lookup/batches')->assertUnauthorized();
    }

    public function test_product_lookup_matches_fg_code_prefix_or_name_and_only_active(): void
    {
        $match = MasterProduct::create(['fg_code' => '602016001', 'product_name' => 'Moko Cushion Fair', 'is_active' => true]);
        MasterProduct::create(['fg_code' => '702000001', 'product_name' => 'Other', 'is_active' => true]);
        MasterProduct::create(['fg_code' => '602016009', 'product_name' => 'Inactive', 'is_active' => false]);
        MasterProductBulkCode::create(['master_product_id' => $match->id, 'bulk_code' => 'BLK-1', 'is_active' => true]);
        MasterProductBulkCode::create(['master_product_id' => $match->id, 'bulk_code' => 'BLK-OFF', 'is_active' => false]);

        $this->actingAs(User::factory()->create())
            ->getJson('/lookup/products?q=6020')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $match->id)
            ->assertJsonPath('0.bulk_codes.0.bulk_code', 'BLK-1')
            ->assertJsonCount(1, '0.bulk_codes');

        $this->actingAs(User::factory()->create())
            ->getJson('/lookup/products?q=cushion')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.fg_code', '602016001');
    }

    public function test_product_lookup_is_capped(): void
    {
        foreach (range(1, 40) as $i) {
            MasterProduct::create(['fg_code' => sprintf('FG%03d', $i), 'product_name' => "Product {$i}", 'is_active' => true]);
        }

        $this->actingAs(User::factory()->create())
            ->getJson('/lookup/products')
            ->assertOk()
            ->assertJsonCount(30);
    }

    public function test_product_lookup_treats_like_wildcards_literally(): void
    {
        MasterProduct::create(['fg_code' => 'FG1', 'product_name' => 'Plain', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->getJson('/lookup/products?q=%25')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_batch_lookup_finds_older_batches_by_no_batch_or_product_name(): void
    {
        $user = User::factory()->create();
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Glowing Cushion', 'is_active' => true]);
        $other = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Lip Tint', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $old = IpcBatch::create(['master_product_id' => $product->id, 'no_batch' => 'OLD-001', 'master_line_id' => $line->id, 'created_by' => $user->id]);
        IpcBatch::create(['master_product_id' => $other->id, 'no_batch' => 'NEW-001', 'master_line_id' => $line->id, 'created_by' => $user->id]);

        $this->actingAs($user)->getJson('/lookup/batches?q=OLD')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $old->id)
            ->assertJsonPath('0.master_product.product_name', 'Glowing Cushion');

        $this->actingAs($user)->getJson('/lookup/batches?q=glowing')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.no_batch', 'OLD-001');
    }

    public function test_batch_index_search_counts_and_pages_without_double_counting(): void
    {
        $user = User::factory()->create();
        $cushion = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Glow Cushion', 'is_active' => true]);
        $tint = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Lip Tint', 'is_active' => true]);
        $make = fn (MasterProduct $p, string $no, string $stage = IpcBatch::STAGE_STARTUP) => IpcBatch::create([
            'master_product_id' => $p->id, 'no_batch' => $no, 'created_by' => $user->id, 'current_stage' => $stage,
        ]);

        // Matches by product name only, by no_batch only, and by both (must count once).
        foreach (range(1, 22) as $i) {
            $make($cushion, sprintf('X%03d', $i));
        }
        $make($tint, 'GLOW-1');
        $both = $make($cushion, 'GLOW-2', IpcBatch::STAGE_FILLING);
        $make($tint, 'OTHER-1');
        $make($cushion, 'X-DELETED')->delete();

        $this->actingAs($user)->get('/batches?q=glow')
            ->assertInertia(fn ($page) => $page
                ->where('batches.total', 24)
                ->has('batches.data', 20)
                ->where('batches.last_page', 2)
                ->where('batches.data.0.id', $both->id)
            );

        $this->actingAs($user)->get('/batches?q=glow&page=2')
            ->assertInertia(fn ($page) => $page->has('batches.data', 4)->where('batches.current_page', 2));

        $this->actingAs($user)->get('/batches?q=glow&stage='.IpcBatch::STAGE_FILLING)
            ->assertInertia(fn ($page) => $page
                ->where('batches.total', 1)
                ->where('batches.data.0.id', $both->id)
            );

        $this->actingAs($user)->get('/batches?q=nothing-matches')
            ->assertInertia(fn ($page) => $page->where('batches.total', 0)->has('batches.data', 0));
    }

    public function test_batch_create_page_no_longer_ships_the_product_master(): void
    {
        MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product', 'is_active' => true]);

        $this->actingAs(User::factory()->create())->get('/batches/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('batches/create')->missing('products'));
    }
}
