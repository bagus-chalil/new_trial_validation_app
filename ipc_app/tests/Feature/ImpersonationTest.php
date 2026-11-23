<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_as_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.impersonate', $target))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    public function test_the_impersonator_is_shared_for_the_banner(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Satu']);
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('users.impersonate', $target));

        $this->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('impersonator.name', 'Admin Satu'));
    }

    public function test_leaving_returns_to_the_admin_account(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('users.impersonate', $target));

        $this->post(route('impersonate.leave'))->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_leaving_without_an_impersonation_session_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('impersonate.leave'))->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $approver = User::factory()->approver()->create();
        $target = User::factory()->create();

        $this->actingAs($approver)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($approver);
    }

    public function test_admin_cannot_impersonate_themselves_or_an_inactive_user(): void
    {
        $admin = User::factory()->admin()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->post(route('users.impersonate', $admin))->assertForbidden();
        $this->actingAs($admin)->post(route('users.impersonate', $inactive))->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_cannot_start_a_second_impersonation_from_inside_one(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $third = User::factory()->create();

        $this->actingAs($admin)->post(route('users.impersonate', $otherAdmin));

        $this->post(route('users.impersonate', $third))->assertForbidden();

        $this->assertAuthenticatedAs($otherAdmin);
    }

    public function test_impersonated_user_only_has_their_own_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();

        $this->actingAs($admin)->post(route('users.impersonate', $staff));

        $this->get(route('users.index'))->assertForbidden();
    }
}
