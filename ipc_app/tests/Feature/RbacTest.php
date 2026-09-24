<?php

namespace Tests\Feature;

use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(?int $createdBy = null): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $createdBy ??= User::factory()->create()->id;

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-001',
            'master_line_id' => $line->id,
            'created_by' => $createdBy,
            'current_stage' => IpcBatch::STAGE_FILLING,
        ]);

        StartupCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $createdBy, 'completed_at' => now()]);

        return $batch->fresh();
    }

    private function makeBatchAtApprovalStage(int $createdBy): IpcBatch
    {
        $batch = $this->makeBatch($createdBy);
        $batch->update(['current_stage' => IpcBatch::STAGE_APPROVAL]);

        FillingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $createdBy, 'save_count' => 1, 'completed_at' => now()]);
        PackingCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $createdBy, 'save_count' => 1, 'completed_at' => now()]);
        // ApprovalController::guardFinished() 403s the whole Approval screen (every route, not
        // just the "finished" stage) unless this exists — so every stage ends up "ready" too.
        FinishedCheck::create(['ipc_batch_id' => $batch->id, 'user_id' => $createdBy, 'completed_at' => now()]);

        return $batch->fresh();
    }

    // --- Role default ---

    public function test_new_user_defaults_to_staff_role(): void
    {
        // The factory doesn't set 'role' at all — asserting against a fresh() read proves the
        // *database column* defaults to 'staff' (via the migration), not just an in-memory value.
        $user = User::factory()->create()->fresh();

        $this->assertSame(User::ROLE_STAFF, $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isApprover());
    }

    // --- Batch ownership (update) ---
    // Batches are shift-worked, not owned: any Staff (or Admin) can continue a batch a different
    // Staff started, so a batch started in shift A/B can be finished by shift C. Approver is the
    // one role excluded from editing batch stage data — it only approves (see the Approval
    // routes tests below).

    public function test_non_creator_staff_can_update_a_batch_someone_else_started(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $batch = $this->makeBatch($owner->id);

        $this->actingAs($other)
            ->get("/batches/{$batch->id}/filling-check")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isReadOnly', false));
    }

    public function test_non_creator_staff_can_upload_a_photo_to_a_batch_someone_else_started(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $batch = $this->makeBatch($owner->id);

        $photo = UploadedFile::fake()->image('color.jpg');
        $this->actingAs($other)
            ->post("/batches/{$batch->id}/filling-check/photo/color", ['photo' => $photo])
            ->assertRedirect("/batches/{$batch->id}/filling-check");
    }

    public function test_creator_can_update_their_own_batch(): void
    {
        $owner = User::factory()->create();
        $batch = $this->makeBatch($owner->id);

        $this->actingAs($owner)
            ->get("/batches/{$batch->id}/filling-check")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isReadOnly', false));
    }

    public function test_admin_can_update_any_batch(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $batch = $this->makeBatch($owner->id);

        $this->actingAs($admin)
            ->get("/batches/{$batch->id}/filling-check")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isReadOnly', false));
    }

    public function test_approver_is_forbidden_from_updating_a_batch(): void
    {
        $owner = User::factory()->create();
        $approver = User::factory()->approver()->create();
        $batch = $this->makeBatch($owner->id);

        $this->actingAs($approver)
            ->put("/batches/{$batch->id}/filling-check", ['finalize' => false])
            ->assertForbidden();
    }

    public function test_approver_is_forbidden_from_creating_a_batch(): void
    {
        $product = MasterProduct::create(['fg_code' => 'FG-3', 'product_name' => 'Product 3', 'is_active' => true]);

        $this->actingAs(User::factory()->approver()->create())
            ->post('/batches', [
                'master_product_id' => $product->id,
                'master_product_bulk_code_id' => 1,
                'no_batch' => 'BATCH-003',
            ])
            ->assertForbidden();
    }

    // --- Approval routes ---

    public function test_staff_is_forbidden_from_every_approval_route(): void
    {
        $owner = User::factory()->create();
        $batch = $this->makeBatchAtApprovalStage($owner->id);

        $this->actingAs($owner)->get('/approvals')->assertForbidden();
        $this->actingAs($owner)->get("/batches/{$batch->id}/approval")->assertForbidden();
        $this->actingAs($owner)
            ->put("/batches/{$batch->id}/approval/startup", ['decision' => 'Approved'])
            ->assertForbidden();
    }

    public function test_approver_and_admin_can_reach_approval_routes(): void
    {
        $approver = User::factory()->approver()->create();
        $admin = User::factory()->admin()->create();
        $batch = $this->makeBatchAtApprovalStage(User::factory()->create()->id);

        $this->actingAs($approver)->get('/approvals')->assertOk();
        $this->actingAs($approver)->get("/batches/{$batch->id}/approval")->assertOk();
        $this->actingAs($admin)->get('/approvals')->assertOk();
        $this->actingAs($admin)->get("/batches/{$batch->id}/approval")->assertOk();
    }

    // --- Approval Queue query correctness ---

    public function test_approval_queue_lists_ready_undecided_batches_with_labels(): void
    {
        $approver = User::factory()->approver()->create();
        $batch = $this->makeBatchAtApprovalStage(User::factory()->create()->id);

        $this->actingAs($approver)->get('/approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('approvals/index')
                ->has('queue', 1)
                ->where('queue.0.batch.id', $batch->id)
                ->where('queue.0.pendingStages', [
                    IpcApproval::STAGE_LABELS[IpcApproval::STAGE_STARTUP],
                    IpcApproval::STAGE_LABELS[IpcApproval::STAGE_FILLING_PACKING],
                    IpcApproval::STAGE_LABELS[IpcApproval::STAGE_FINISHED],
                ]));
    }

    public function test_approval_queue_excludes_batch_with_all_stages_approved(): void
    {
        $approver = User::factory()->approver()->create();
        $batch = $this->makeBatchAtApprovalStage(User::factory()->create()->id);

        foreach (IpcApproval::STAGES as $stage) {
            IpcApproval::create([
                'ipc_batch_id' => $batch->id,
                'stage' => $stage,
                'decision' => IpcApproval::DECISION_APPROVED,
                'approver_user_id' => $approver->id,
                'approved_at' => now(),
            ]);
        }
        $batch->update(['current_stage' => IpcBatch::STAGE_PRINT]);

        $this->actingAs($approver)->get('/approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('queue', 0));
    }

    public function test_approval_queue_excludes_batch_with_zero_ready_stages(): void
    {
        $approver = User::factory()->approver()->create();
        $product = MasterProduct::create(['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'is_active' => true]);
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 02', 'name' => 'Make Up 02', 'is_active' => true]);

        // No Finished Check at all — the query's own whereHas('finishedCheck', ...) filter
        // (mirroring ApprovalController::guardFinished()) already excludes this on its own,
        // before stageReady() even gets a chance to run.
        IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'BATCH-002',
            'master_line_id' => $line->id,
            'created_by' => User::factory()->create()->id,
            'current_stage' => IpcBatch::STAGE_FILLING,
        ]);

        $this->actingAs($approver)->get('/approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('queue', 0));
    }

    public function test_approval_queue_excludes_batch_with_a_ready_stage_but_no_finished_check(): void
    {
        $approver = User::factory()->approver()->create();
        // makeBatch() completes Startup (so the 'startup' stage IS individually "ready") but the
        // batch never reached Finished Check — ApprovalController::guardFinished() 403s every
        // approval route in that state, so this batch must not appear in the queue even though
        // stageReady('startup') is true. Regression test for a real bug caught via live
        // verification: an earlier version of this query only excluded current_stage === startup,
        // which let a batch exactly like this one appear with a dead 403 link.
        $this->makeBatch(User::factory()->create()->id);

        $this->actingAs($approver)->get('/approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('queue', 0));
    }

    public function test_approval_queue_includes_batch_with_a_rejected_ready_stage(): void
    {
        $approver = User::factory()->approver()->create();
        $batch = $this->makeBatchAtApprovalStage(User::factory()->create()->id);

        IpcApproval::create([
            'ipc_batch_id' => $batch->id,
            'stage' => IpcApproval::STAGE_STARTUP,
            'decision' => IpcApproval::DECISION_REJECTED,
            'approver_user_id' => $approver->id,
            'remarks' => 'Cacat',
            'approved_at' => now(),
        ]);

        $this->actingAs($approver)->get('/approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('queue', 1));
    }

    // --- Master Data routes ---

    public function test_staff_is_forbidden_from_master_data_routes(): void
    {
        $this->actingAs(User::factory()->create())->get('/masters/lines')->assertForbidden();
    }

    public function test_admin_can_reach_master_data_routes(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/masters/lines')->assertOk();
    }

    // --- User role management ---

    public function test_staff_is_forbidden_from_user_role_management(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
    }

    public function test_approver_is_forbidden_from_user_role_management(): void
    {
        $this->actingAs(User::factory()->approver()->create())->get('/users')->assertForbidden();
    }

    public function test_admin_can_view_user_role_management(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/users')->assertOk();
    }

    public function test_admin_can_persist_a_role_change(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/users/{$target->id}/role", ['role' => User::ROLE_APPROVER])
            ->assertRedirect();

        $this->assertSame(User::ROLE_APPROVER, $target->fresh()->role);
    }

    public function test_role_update_rejects_an_out_of_list_value(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/users/{$target->id}/role", ['role' => 'superuser'])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_STAFF, $target->fresh()->role);
    }

    // --- Admin-driven user creation (no public registration) ---

    public function test_registration_page_no_longer_exists(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_staff_is_forbidden_from_creating_a_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/users', [
                'name' => 'New Guy',
                'email' => 'newguy@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_STAFF,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'New Guy',
                'email' => 'newguy@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_APPROVER,
            ])
            ->assertRedirect();

        $created = User::where('email', 'newguy@example.com')->firstOrFail();
        $this->assertSame('New Guy', $created->name);
        $this->assertSame(User::ROLE_APPROVER, $created->role);
        $this->assertTrue(Hash::check('password', $created->password));
    }

    public function test_user_creation_rejects_a_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create();

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'Duplicate',
                'email' => $existing->email,
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_STAFF,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_creation_rejects_a_mismatched_password_confirmation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'New Guy',
                'email' => 'newguy2@example.com',
                'password' => 'password',
                'password_confirmation' => 'nope',
                'role' => User::ROLE_STAFF,
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'newguy2@example.com']);
    }

    // --- Admin-driven full user edit ---

    public function test_staff_is_forbidden_from_editing_a_user(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch("/users/{$target->id}", [
                'name' => 'Renamed',
                'email' => $target->email,
                'role' => User::ROLE_STAFF,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_edit_a_users_name_email_and_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/users/{$target->id}", [
                'name' => 'Renamed User',
                'email' => 'renamed@example.com',
                'role' => User::ROLE_APPROVER,
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('Renamed User', $target->name);
        $this->assertSame('renamed@example.com', $target->email);
        $this->assertSame(User::ROLE_APPROVER, $target->role);
    }

    public function test_user_edit_rejects_a_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create()->fresh();
        $other = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $other->email,
                'role' => $target->role,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_editing_a_user_without_a_password_leaves_it_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create()->fresh();
        $originalHash = $target->password;

        $this->actingAs($admin)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
            ])
            ->assertRedirect();

        $this->assertSame($originalHash, $target->fresh()->password);
    }

    public function test_admin_can_reset_a_users_password_via_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create()->fresh();

        $this->actingAs($admin)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
    }

    public function test_password_reset_via_edit_rejects_a_mismatched_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create()->fresh();
        $originalHash = $target->password;

        $this->actingAs($admin)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'password' => 'new-password',
                'password_confirmation' => 'nope',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($originalHash, $target->fresh()->password);
    }

    // --- Active/inactive toggle (soft delete) ---

    public function test_new_user_defaults_to_active(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertTrue($user->is_active);
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->patch("/users/{$target->id}/status")->assertRedirect();
        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($admin)->patch("/users/{$target->id}/status")->assertRedirect();
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/users/{$admin->id}/status")->assertRedirect();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_staff_is_forbidden_from_toggling_user_status(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch("/users/{$target->id}/status")
            ->assertForbidden();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
