<?php

use App\Models\ActivityLog;
use App\Models\User;

test('admin can log in as another user and is logged', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $target = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $target))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($target);
    expect(session('impersonator_id'))->toBe($admin->id);
    expect(ActivityLog::where('action', 'IMPERSONATE_START')->where('user_id', $admin->id)->where('record_id', (string) $target->id)->exists())->toBeTrue();
});

test('the impersonator is shared to the frontend for the banner', function () {
    $admin = User::factory()->create(['role' => 'Admin', 'name' => 'Admin Satu']);
    $target = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('impersonator.name', 'Admin Satu'));
});

test('leaving impersonation returns to the admin account', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $target = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

    $this->post(route('admin.impersonate.leave'))
        ->assertRedirect(route('admin.users.index'));

    $this->assertAuthenticatedAs($admin);
    expect(session('impersonator_id'))->toBeNull();
    expect(ActivityLog::where('action', 'IMPERSONATE_END')->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('leaving without an impersonation session is forbidden', function () {
    $user = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($user)->post(route('admin.impersonate.leave'))->assertForbidden();
    $this->assertAuthenticatedAs($user);
});

test('non-admin cannot impersonate', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $target = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($staff)->post(route('admin.users.impersonate', $target))->assertForbidden();
    $this->assertAuthenticatedAs($staff);
});

test('admin cannot impersonate a super admin, but a super admin can', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $otherSuperAdmin = User::factory()->create(['role' => 'Super Admin']);

    $this->actingAs($admin)->post(route('admin.users.impersonate', $superAdmin))->assertForbidden();

    $this->actingAs($otherSuperAdmin)->post(route('admin.users.impersonate', $superAdmin))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($superAdmin);
});

test('admin cannot impersonate an inactive user or themselves', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $inactive = User::factory()->create(['role' => 'Staff', 'is_active' => 0]);

    $this->actingAs($admin)->post(route('admin.users.impersonate', $inactive))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.impersonate', $admin))->assertForbidden();
});

test('cannot start a second impersonation from inside one', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $target = User::factory()->create(['role' => 'Admin']);
    $third = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

    $this->post(route('admin.users.impersonate', $third))->assertForbidden();
    $this->assertAuthenticatedAs($target);
});
