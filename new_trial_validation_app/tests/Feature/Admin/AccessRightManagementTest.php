<?php

use App\Models\MasterOption;
use App\Models\Trial;
use App\Models\TrialEditPermission;
use App\Models\User;

function makeDraftTrial(array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-'.uniqid(),
        'product_name' => 'Sample Product',
        'finish_good_code' => 'FG-001',
        'product_type' => 'Tube',
        'progress_status' => 'Draft',
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));
}

test('non-super-admin cannot view access rights', function () {
    $admin = User::factory()->create(['role' => 'Admin']);

    $this->actingAs($admin)
        ->get(route('admin.access-rights.index'))
        ->assertForbidden();
});

test('super admin can view access rights', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $this->actingAs($superAdmin)
        ->get(route('admin.access-rights.index'))
        ->assertOk();
});

test('super admin cannot change their own role from this screen', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $response = $this->actingAs($superAdmin)->post(route('admin.access-rights.users.role', $superAdmin), [
        'app_role' => 'Admin',
        'department' => '',
    ]);

    $response->assertSessionHasErrors('app_role');
    expect($superAdmin->refresh()->role)->toBe('Super Admin');
    expect($superAdmin->app_role)->toBeNull();
});

test('super admin can reassign another user\'s app_role; the legacy role/department columns are left untouched', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $target = User::factory()->create(['role' => 'Viewer', 'department' => null]);

    // This screen writes app_role, never the legacy-shared `role` column
    // (Phase 2 of the RBAC/Team-master redesign — legacy hardcodes literal
    // checks against role strings it must keep reading unchanged) — and no
    // longer edits `department` either, even if a stray 'department' key
    // were submitted, since that field was removed from the form on purpose.
    $response = $this->actingAs($superAdmin)->post(route('admin.access-rights.users.role', $target), [
        'app_role' => 'Staff',
        'department' => 'ops',
    ]);

    $response->assertRedirect(route('admin.access-rights.index'));
    $target->refresh();
    expect($target->app_role)->toBe('Staff');
    expect($target->role)->toBe('Viewer');
    expect($target->department)->toBeNull();
});

test('super admin can assign a review team independently of role/department', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $target = User::factory()->create(['role' => 'Staff', 'department' => 'Ops']);
    $team = MasterOption::where('type', 'reviewer_department')->where('name', 'PROD')->firstOrFail();

    $this->actingAs($superAdmin)->post(route('admin.access-rights.users.role', $target), [
        'app_role' => 'Staff',
        'review_team_id' => $team->id,
    ]);

    $target->refresh();
    expect($target->app_role)->toBe('Staff');
    expect($target->department)->toBe('Ops');
    expect($target->review_team_id)->toBe($team->id);
    // review_unit (the legacy free-text column) is kept in sync for backward
    // compatibility, since some code still queries it directly.
    expect($target->review_unit)->toBe('PROD');
});

test('review team can be cleared back to none via the __none sentinel', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $team = MasterOption::where('type', 'reviewer_department')->where('name', 'PROD')->firstOrFail();
    $target = User::factory()->create(['role' => 'Staff', 'review_team_id' => $team->id, 'review_unit' => 'PROD']);

    $this->actingAs($superAdmin)->post(route('admin.access-rights.users.role', $target), [
        'app_role' => 'Staff',
        'department' => '',
        'review_team_id' => '__none',
    ]);

    $target->refresh();
    expect($target->review_team_id)->toBeNull();
    expect($target->review_unit)->toBeNull();
});

test('an unknown review team value is rejected', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $target = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($superAdmin)->post(route('admin.access-rights.users.role', $target), [
        'app_role' => 'Staff',
        'department' => '',
        'review_team_id' => 999999,
    ]);

    $response->assertSessionHasErrors('review_team_id');
});

test('reassigning to an unknown role is rejected', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $target = User::factory()->create(['role' => 'Viewer']);

    $response = $this->actingAs($superAdmin)->post(route('admin.access-rights.users.role', $target), [
        'app_role' => 'Not A Real Role',
        'department' => '',
    ]);

    $response->assertSessionHasErrors('app_role');
    expect($target->refresh()->role)->toBe('Viewer');
    expect($target->app_role)->toBeNull();
});

test('admin cannot reassign roles', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $target = User::factory()->create(['role' => 'Viewer']);

    $this->actingAs($admin)->post(route('admin.access-rights.users.role', $target), [
        'app_role' => 'Staff',
    ])->assertForbidden();

    expect($target->refresh()->role)->toBe('Viewer');
});

test('super admin can add a reviewer department', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $this->actingAs($superAdmin)->post(route('admin.access-rights.reviewer-departments.store'), [
        'name' => 'new dept',
        'sort_order' => 3,
    ])->assertRedirect(route('admin.access-rights.index'));

    $option = MasterOption::where('type', 'reviewer_department')->where('name', 'NEW DEPT')->first();
    expect($option)->not->toBeNull();
    expect($option->sort_order)->toBe(3);
    expect($option->is_active)->toBeTrue();
});

test('saving a reviewer department with the same name updates it instead of duplicating', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $existing = MasterOption::create(['type' => 'reviewer_department', 'name' => 'DEPT X', 'sort_order' => 1, 'is_active' => true]);

    $this->actingAs($superAdmin)->post(route('admin.access-rights.reviewer-departments.store'), [
        'name' => 'dept x',
        'sort_order' => 9,
    ])->assertRedirect(route('admin.access-rights.index'));

    // Not an exact-1 count: the Phase 1 migration seeds the 5 hardcoded
    // default codes (PROD/RNI/QAC/PRNI/PI) as real master_options rows too,
    // so 'DEPT X' specifically must stay a single row, not the whole table.
    expect(MasterOption::where('type', 'reviewer_department')->where('name', 'DEPT X')->count())->toBe(1);
    expect($existing->refresh()->sort_order)->toBe(9);
});

test('super admin can soft delete and re-add a reviewer department', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $option = MasterOption::create(['type' => 'reviewer_department', 'name' => 'DEPT Y', 'sort_order' => 1, 'is_active' => true]);

    $this->actingAs($superAdmin)->delete(route('admin.access-rights.reviewer-departments.destroy', $option))
        ->assertRedirect(route('admin.access-rights.index'));

    $option->refresh();
    expect($option->is_active)->toBeFalse();
    expect($option->deleted_at)->not->toBeNull();

    $this->actingAs($superAdmin)->post(route('admin.access-rights.reviewer-departments.store'), [
        'name' => 'DEPT Y',
        'sort_order' => 5,
    ]);

    $option->refresh();
    // Not an exact-1 count: the Phase 1 migration seeds the 5 hardcoded
    // default codes (PROD/RNI/QAC/PRNI/PI) as real master_options rows too,
    // so 'DEPT Y' specifically must stay a single row, not the whole table.
    expect(MasterOption::where('type', 'reviewer_department')->where('name', 'DEPT Y')->count())->toBe(1);
    expect($option->is_active)->toBeTrue();
    expect($option->deleted_at)->toBeNull();
    expect($option->sort_order)->toBe(5);
});

test('super admin can rename a reviewer department', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    // Reuse one of the 5 default codes the Phase 1 migration seeds into
    // master_options (rather than creating a fresh 'PI' row, which would
    // collide with the unique(type,name) index) — this is also the exact
    // real-world PI -> PIE rename scenario from the approved plan.
    $option = MasterOption::where('type', 'reviewer_department')->where('name', 'PI')->firstOrFail();
    $user = User::factory()->create(['role' => 'Staff', 'review_team_id' => $option->id]);

    $this->actingAs($superAdmin)->put(route('admin.access-rights.reviewer-departments.update', $option), [
        'name' => 'PIE',
        'sort_order' => 2,
    ])->assertRedirect(route('admin.access-rights.index'));

    $option->refresh();
    expect($option->name)->toBe('PIE');
    expect($option->sort_order)->toBe(2);

    // The rename is instantly visible to anything referencing the team by
    // id — the whole point of Phase 1 (RBAC/Team-master redesign).
    expect($user->fresh()->reviewTeam->name)->toBe('PIE');
    expect($user->fresh()->reviewDepartmentsForUser())->toBe(['PIE']);
});

test('renaming a reviewer department to a name already used by another active row is rejected', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $option = MasterOption::where('type', 'reviewer_department')->where('name', 'PI')->firstOrFail();

    $response = $this->actingAs($superAdmin)->put(route('admin.access-rights.reviewer-departments.update', $option), [
        'name' => 'qac',
    ]);

    $response->assertSessionHasErrors('name');
    expect($option->refresh()->name)->toBe('PI');
});

test('renaming a reviewer department to its own current name is allowed', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $option = MasterOption::where('type', 'reviewer_department')->where('name', 'PI')->firstOrFail();

    $this->actingAs($superAdmin)->put(route('admin.access-rights.reviewer-departments.update', $option), [
        'name' => 'pi',
        'sort_order' => 7,
    ])->assertRedirect(route('admin.access-rights.index'));

    expect($option->refresh()->sort_order)->toBe(7);
});

test('admin (non-super-admin) cannot rename a reviewer department', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $option = MasterOption::where('type', 'reviewer_department')->where('name', 'PI')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.access-rights.reviewer-departments.update', $option), [
        'name' => 'PIE',
    ])->assertForbidden();

    expect($option->refresh()->name)->toBe('PI');
});

test('renaming a reviewer department requires it to actually be one', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $option = MasterOption::create(['type' => 'product_type', 'name' => 'Tube', 'sort_order' => 1, 'is_active' => true]);

    $this->actingAs($superAdmin)->put(route('admin.access-rights.reviewer-departments.update', $option), [
        'name' => 'Not A Team',
    ])->assertNotFound();
});

test('deleting a reviewer department requires it to actually be one', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $option = MasterOption::create(['type' => 'product_type', 'name' => 'Tube', 'sort_order' => 1, 'is_active' => true]);

    $this->actingAs($superAdmin)->delete(route('admin.access-rights.reviewer-departments.destroy', $option))
        ->assertNotFound();
});

test('super admin can grant draft edit permission to a staff user', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $staff = User::factory()->create(['role' => 'Staff', 'email' => 'staff@local.test']);
    $trial = makeDraftTrial(['created_by' => 'owner@local.test']);

    $this->actingAs($superAdmin)->post(route('admin.access-rights.draft-permissions.store'), [
        'trial_id' => $trial->id,
        'user_id' => $staff->id,
    ])->assertRedirect(route('admin.access-rights.index'));

    $permission = TrialEditPermission::where('trial_id', $trial->id)->where('user_id', $staff->id)->first();
    expect($permission)->not->toBeNull();
    expect($permission->can_edit)->toBeTrue();
    expect($permission->granted_by)->toBe($superAdmin->id);
    expect($permission->revoked_at)->toBeNull();
});

test('granting permission to the trial owner is rejected', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $staff = User::factory()->create(['role' => 'Staff', 'email' => 'owner@local.test']);
    $trial = makeDraftTrial(['created_by' => 'owner@local.test']);

    $response = $this->actingAs($superAdmin)->post(route('admin.access-rights.draft-permissions.store'), [
        'trial_id' => $trial->id,
        'user_id' => $staff->id,
    ]);

    $response->assertSessionHasErrors('user_id');
    expect(TrialEditPermission::count())->toBe(0);
});

test('granting permission for a non-draft trial or non-staff user is rejected', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $viewer = User::factory()->create(['role' => 'Viewer']);
    $approvedTrial = makeDraftTrial(['progress_status' => 'Approved']);
    $draftTrial = makeDraftTrial();

    $this->actingAs($superAdmin)->post(route('admin.access-rights.draft-permissions.store'), [
        'trial_id' => $approvedTrial->id,
        'user_id' => $viewer->id,
    ])->assertSessionHasErrors('trial_id');

    $this->actingAs($superAdmin)->post(route('admin.access-rights.draft-permissions.store'), [
        'trial_id' => $draftTrial->id,
        'user_id' => $viewer->id,
    ])->assertSessionHasErrors('trial_id');

    expect(TrialEditPermission::count())->toBe(0);
});

test('super admin can revoke a granted permission', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $staff = User::factory()->create(['role' => 'Staff']);
    $trial = makeDraftTrial();
    $permission = TrialEditPermission::create([
        'trial_id' => $trial->id,
        'user_id' => $staff->id,
        'can_edit' => true,
        'granted_by' => $superAdmin->id,
        'granted_at' => now(),
    ]);

    $this->actingAs($superAdmin)->post(route('admin.access-rights.draft-permissions.revoke', $permission))
        ->assertRedirect(route('admin.access-rights.index'));

    $permission->refresh();
    expect($permission->can_edit)->toBeFalse();
    expect($permission->revoked_by)->toBe($superAdmin->id);
    expect($permission->revoked_at)->not->toBeNull();
});

test('re-granting a revoked permission reactivates the same row', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $staff = User::factory()->create(['role' => 'Staff']);
    $trial = makeDraftTrial();
    $permission = TrialEditPermission::create([
        'trial_id' => $trial->id,
        'user_id' => $staff->id,
        'can_edit' => false,
        'granted_by' => $superAdmin->id,
        'granted_at' => now()->subDay(),
        'revoked_by' => $superAdmin->id,
        'revoked_at' => now(),
    ]);

    $this->actingAs($superAdmin)->post(route('admin.access-rights.draft-permissions.store'), [
        'trial_id' => $trial->id,
        'user_id' => $staff->id,
    ]);

    expect(TrialEditPermission::count())->toBe(1);
    $permission->refresh();
    expect($permission->can_edit)->toBeTrue();
    expect($permission->revoked_at)->toBeNull();
});

test('reviewDepartmentsForUser() sources the team name from review_team_id when set', function () {
    $team = MasterOption::where('type', 'reviewer_department')->where('name', 'QAC')->firstOrFail();
    $user = User::factory()->create(['role' => 'Staff', 'review_team_id' => $team->id, 'review_unit' => null]);

    expect($user->reviewDepartmentsForUser())->toBe(['QAC']);
});

test('reviewDepartmentsForUser() falls back to the legacy review_unit column when review_team_id is null', function () {
    $user = User::factory()->create(['role' => 'Staff', 'review_team_id' => null, 'review_unit' => 'RNI']);

    expect($user->reviewDepartmentsForUser())->toBe(['RNI']);
});

test('reviewDepartmentsForUser() reflects a team rename immediately via review_team_id, unlike the review_unit fallback', function () {
    $team = MasterOption::where('type', 'reviewer_department')->where('name', 'PI')->firstOrFail();
    $byId = User::factory()->create(['role' => 'Staff', 'review_team_id' => $team->id, 'review_unit' => 'PI']);
    $byUnit = User::factory()->create(['role' => 'Staff', 'review_team_id' => null, 'review_unit' => 'PI']);

    $team->update(['name' => 'PIE']);

    expect($byId->fresh()->reviewDepartmentsForUser())->toBe(['PIE']);
    expect($byUnit->fresh()->reviewDepartmentsForUser())->toBe(['PI']);
});
