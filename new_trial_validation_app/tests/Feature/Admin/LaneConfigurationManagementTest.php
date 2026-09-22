<?php

use App\Models\LineConfigurationLane;
use App\Models\MasterOption;
use App\Models\User;

test('non-super-admin cannot view lane configuration', function () {
    $admin = User::factory()->create(['role' => 'Admin']);

    $this->actingAs($admin)
        ->get(route('admin.lane-configuration.index'))
        ->assertForbidden();
});

test('super admin can view lane configuration with both stages and the review teams picker', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $response = $this->actingAs($superAdmin)->get(route('admin.lane-configuration.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('lanes', 2)
        ->has('reviewTeams'));
});

test('super admin can rename a lane and change its required team', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $lane = LineConfigurationLane::where('stage_key', 'approved_pie')->firstOrFail();
    $qacTeam = MasterOption::firstWhere(['type' => 'reviewer_department', 'name' => 'QAC']);

    $response = $this->actingAs($superAdmin)->put(route('admin.lane-configuration.update', $lane), [
        'label' => 'Approved (Line Head)',
        'required_team_id' => $qacTeam->id,
    ]);

    $response->assertRedirect(route('admin.lane-configuration.index'));

    $lane->refresh();
    expect($lane->label)->toBe('Approved (Line Head)');
    expect($lane->required_team_id)->toBe($qacTeam->id);
});

test('the "Semua user" sentinel clears a lane\'s required team back to unrestricted', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $lane = LineConfigurationLane::where('stage_key', 'checked_prod')->firstOrFail();
    expect($lane->required_team_id)->not->toBeNull();

    $this->actingAs($superAdmin)->put(route('admin.lane-configuration.update', $lane), [
        'label' => $lane->label,
        'required_team_id' => '__none',
    ])->assertRedirect(route('admin.lane-configuration.index'));

    $lane->refresh();
    expect($lane->required_team_id)->toBeNull();
});

test('a non-existent or inactive review team id is rejected', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $lane = LineConfigurationLane::where('stage_key', 'approved_pie')->firstOrFail();

    $this->actingAs($superAdmin)
        ->put(route('admin.lane-configuration.update', $lane), [
            'label' => 'Approved (PIE)',
            'required_team_id' => 999999,
        ])
        ->assertInvalid(['required_team_id']);
});

test('staff cannot update lane configuration', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $lane = LineConfigurationLane::where('stage_key', 'approved_pie')->firstOrFail();

    $this->actingAs($staff)
        ->put(route('admin.lane-configuration.update', $lane), ['label' => 'Hacked'])
        ->assertForbidden();

    expect($lane->refresh()->label)->not->toBe('Hacked');
});
