<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function batch(string $noBatch, string $stage = IpcBatch::STAGE_COMPLETED): IpcBatch
    {
        $product = MasterProduct::firstOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Product 1', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => $noBatch,
            'created_by' => User::factory()->create()->id,
            'current_stage' => $stage,
        ]);
    }

    public function test_admin_can_archive_a_completed_batch_and_it_leaves_the_batch_list(): void
    {
        $admin = User::factory()->admin()->create();
        $archived = $this->batch('B-DONE');
        $this->batch('B-ACTIVE', IpcBatch::STAGE_FILLING);

        $this->actingAs($admin)->post("/batches/{$archived->id}/archive")->assertSessionHasNoErrors();

        $archived->refresh();
        $this->assertNotNull($archived->archived_at);
        $this->assertSame($admin->id, $archived->archived_by);

        $this->get('/batches')->assertInertia(fn ($page) => $page->where('batches.total', 1)->where('batches.data.0.no_batch', 'B-ACTIVE'));
        $this->get('/batches?q=B-DONE')->assertInertia(fn ($page) => $page->where('batches.total', 0));

        $this->get('/archive')->assertOk()->assertInertia(fn ($page) => $page->component('archive/index')
            ->where('batches.total', 1)
            ->where('batches.data.0.no_batch', 'B-DONE')
            ->where('batches.data.0.archived_by_user.name', $admin->name));
        $this->get('/archive?q=B-DONE')->assertInertia(fn ($page) => $page->where('batches.total', 1));
        $this->get('/archive?q=B-ACTIVE')->assertInertia(fn ($page) => $page->where('batches.total', 0));

        // Archived batches stay viewable.
        $this->get("/batches/{$archived->id}")->assertOk();
    }

    public function test_an_unfinished_batch_cannot_be_archived(): void
    {
        $batch = $this->batch('B-ACTIVE', IpcBatch::STAGE_APPROVAL);

        $this->actingAs(User::factory()->admin()->create())
            ->post("/batches/{$batch->id}/archive")
            ->assertSessionHasErrors('archive');

        $this->assertNull($batch->fresh()->archived_at);
    }

    public function test_admin_can_unarchive_a_batch(): void
    {
        $batch = $this->batch('B-DONE');
        $batch->forceFill(['archived_at' => now()])->save();

        $this->actingAs(User::factory()->admin()->create())->delete("/batches/{$batch->id}/archive");

        $this->assertNull($batch->fresh()->archived_at);
        $this->get('/batches')->assertInertia(fn ($page) => $page->where('batches.total', 1));
    }

    public function test_admin_can_soft_delete_a_batch_and_restore_it_from_the_recycle_bin(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = $this->batch('B-TEST', IpcBatch::STAGE_STARTUP);

        $this->actingAs($admin)->from('/batches')->delete("/batches/{$batch->id}")->assertRedirect('/batches');

        $this->assertSoftDeleted($batch);
        $this->assertSame($admin->id, IpcBatch::withTrashed()->find($batch->id)->deleted_by);
        $this->get('/batches')->assertInertia(fn ($page) => $page->where('batches.total', 0));
        $this->get('/masters/recycle-bin')->assertInertia(fn ($page) => $page->where('counts.batches', 1));

        $this->patch("/masters/recycle-bin/batches/{$batch->id}/restore");

        $this->assertNotSoftDeleted($batch);
    }

    public function test_deleting_from_the_batch_page_redirects_to_the_batch_list(): void
    {
        $batch = $this->batch('B-TEST', IpcBatch::STAGE_STARTUP);

        $this->actingAs(User::factory()->admin()->create())
            ->from("/batches/{$batch->id}")
            ->delete("/batches/{$batch->id}")
            ->assertRedirect('/batches');
    }

    public function test_non_admins_cannot_archive_delete_or_open_the_archive(): void
    {
        $batch = $this->batch('B-DONE');

        foreach ([User::factory()->create(), User::factory()->approver()->create()] as $user) {
            $this->actingAs($user);
            $this->get('/archive')->assertForbidden();
            $this->post("/batches/{$batch->id}/archive")->assertForbidden();
            $this->delete("/batches/{$batch->id}/archive")->assertForbidden();
            $this->delete("/batches/{$batch->id}")->assertForbidden();
        }

        $batch->refresh();
        $this->assertNull($batch->archived_at);
        $this->assertNotSoftDeleted($batch);
    }
}
