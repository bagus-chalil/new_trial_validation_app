<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/batches')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_batch_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/batches')->assertOk();
    }

    public function test_create_page_preselects_the_product_given_in_the_query(): void
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BC-ACTIVE', 'is_active' => true]);
        MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BC-INACTIVE', 'is_active' => false]);
        $inactive = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'is_active' => false]);

        $this->actingAs(User::factory()->create());

        $this->get("/batches/create?product={$product->id}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('initialProduct.id', $product->id)
                    ->has('initialProduct.bulk_codes', 1)
                    ->where('initialProduct.bulk_codes.0.bulk_code', 'BC-ACTIVE'),
            );

        $this->get("/batches/create?product={$inactive->id}")
            ->assertInertia(fn ($page) => $page->where('initialProduct', null));

        $this->get('/batches/create')
            ->assertInertia(fn ($page) => $page->where('initialProduct', null));
    }

    public function test_authenticated_user_can_create_a_batch_and_is_redirected_to_startup_check(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $bulkCode = MasterProductBulkCode::create([
            'master_product_id' => $product->id,
            'bulk_code' => 'BULK-1',
            'is_active' => true,
        ]);

        $response = $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$bulkCode->id],
            'no_batch' => 'BATCH-001',
            'flow_type' => IpcBatch::FLOW_FULL,
        ]);

        $batch = IpcBatch::firstOrFail();
        $response->assertRedirect("/batches/{$batch->id}/startup-check");

        $this->assertSame('BATCH-001', $batch->no_batch);
        // Mixing Date and Line are chosen later, on the Startup Check form — see StartupCheckTest.
        $this->assertNull($batch->mixing_date);
        $this->assertNull($batch->master_line_id);
        $this->assertSame('BULK-1', $batch->bulk_code);
        $this->assertSame($bulkCode->id, $batch->master_product_bulk_code_id);
        $this->assertSame(IpcBatch::STAGE_STARTUP, $batch->current_stage);
        $this->assertSame(IpcBatch::FLOW_FULL, $batch->flow_type);
    }

    public function test_flow_type_is_required_to_create_a_batch(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $bulkCode = MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BULK-1', 'is_active' => true]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$bulkCode->id],
            'no_batch' => 'BATCH-001',
        ])->assertSessionHasErrors('flow_type');

        $this->assertSame(0, IpcBatch::count());
    }

    public function test_invalid_flow_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $bulkCode = MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BULK-1', 'is_active' => true]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$bulkCode->id],
            'no_batch' => 'BATCH-001',
            'flow_type' => 'bogus',
        ])->assertSessionHasErrors('flow_type');

        $this->assertSame(0, IpcBatch::count());
    }

    public function test_batch_can_be_created_with_each_flow_type(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);

        foreach (IpcBatch::FLOW_TYPES as $flowType) {
            $bulkCode = MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => "BULK-{$flowType}", 'is_active' => true]);

            $this->post('/batches', [
                'master_product_id' => $product->id,
                'master_product_bulk_code_ids' => [$bulkCode->id],
                'no_batch' => "BATCH-{$flowType}",
                'flow_type' => $flowType,
            ])->assertSessionHasNoErrors();

            $this->assertSame($flowType, IpcBatch::where('no_batch', "BATCH-{$flowType}")->sole()->flow_type);
        }
    }

    public function test_no_batch_is_uppercased(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $bulkCode = MasterProductBulkCode::create([
            'master_product_id' => $product->id,
            'bulk_code' => 'BULK-1',
            'is_active' => true,
        ]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$bulkCode->id],
            'no_batch' => 'batch-typed-lowercase',
            'flow_type' => IpcBatch::FLOW_FULL,
        ]);

        $this->assertSame('BATCH-TYPED-LOWERCASE', IpcBatch::firstOrFail()->no_batch);
    }

    public function test_batch_can_be_created_with_multiple_bulk_codes(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $bulkB = MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BULK-B', 'is_active' => true]);
        $bulkA = MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BULK-A', 'is_active' => true]);
        MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BULK-C', 'is_active' => true]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$bulkB->id, $bulkA->id],
            'no_batch' => 'batch-multi',
            'flow_type' => IpcBatch::FLOW_FULL,
        ])->assertSessionHasNoErrors();

        $batch = IpcBatch::with('bulkCodes')->sole();
        $this->assertSame('BULK-A, BULK-B', $batch->bulk_code);
        $this->assertSame($bulkA->id, $batch->master_product_bulk_code_id);
        $this->assertEqualsCanonicalizing([$bulkA->id, $bulkB->id], $batch->bulkCodes->pluck('master_product_bulk_code_id')->all());
    }

    public function test_at_least_one_bulk_code_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [],
            'no_batch' => 'BATCH-001',
        ])->assertSessionHasErrors('master_product_bulk_code_ids');

        $this->assertSame(0, IpcBatch::count());
    }

    public function test_inactive_bulk_code_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $active = MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $inactive = MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BULK-2', 'is_active' => false]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$active->id, $inactive->id],
            'no_batch' => 'BATCH-001',
        ])->assertSessionHasErrors('master_product_bulk_code_ids.1');

        $this->assertSame(0, IpcBatch::count());
    }

    public function test_no_batch_is_required_to_create_a_batch(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $bulkCode = MasterProductBulkCode::create([
            'master_product_id' => $product->id,
            'bulk_code' => 'BULK-1',
            'is_active' => true,
        ]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$bulkCode->id],
        ])->assertSessionHasErrors('no_batch');

        $this->assertSame(0, IpcBatch::count());
    }

    public function test_bulk_code_from_a_different_product_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $otherProduct = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'is_active' => true]);
        $otherBulkCode = MasterProductBulkCode::create([
            'master_product_id' => $otherProduct->id,
            'bulk_code' => 'BULK-2',
            'is_active' => true,
        ]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$otherBulkCode->id],
        ])->assertSessionHasErrors('master_product_bulk_code_ids.0');
    }

    public function test_authenticated_user_can_view_batch_show_page(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => auth()->id(),
            'current_stage' => IpcBatch::STAGE_FILLING,
        ]);

        $response = $this->get("/batches/{$batch->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('batches/show')
            ->where('stages.0.key', IpcBatch::STAGE_STARTUP)
            ->where('stages.0.status', 'done')
            ->where('stages.1.key', IpcBatch::STAGE_FILLING)
            ->where('stages.1.status', 'active')
            ->where('stages.2.status', 'locked')
        );
    }

    public function test_inactive_product_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => false]);
        $bulkCode = MasterProductBulkCode::create([
            'master_product_id' => $product->id,
            'bulk_code' => 'BULK-1',
            'is_active' => true,
        ]);
        $this->post('/batches', [
            'master_product_id' => $product->id,
            'master_product_bulk_code_ids' => [$bulkCode->id],
        ])->assertSessionHasErrors('master_product_id');
    }
}
