<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
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

    public function test_authenticated_user_can_create_a_batch_and_is_redirected_to_startup_check(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $response = $this->post('/batches', [
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
        ]);

        $batch = IpcBatch::firstOrFail();
        $response->assertRedirect("/batches/{$batch->id}/startup-check");

        $this->assertSame('BATCH-001', $batch->no_batch);
        $this->assertSame(IpcBatch::STAGE_STARTUP, $batch->current_stage);
    }

    public function test_authenticated_user_can_view_batch_show_page(): void
    {
        $this->actingAs(User::factory()->create());

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => true]);
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

        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'bulk_code' => 'BULK-1', 'is_active' => false]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $this->post('/batches', [
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
        ])->assertSessionHasErrors('master_product_id');
    }
}
