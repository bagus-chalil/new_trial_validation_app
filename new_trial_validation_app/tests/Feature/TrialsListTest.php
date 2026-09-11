<?php

use App\Models\Trial;
use App\Models\User;

function makeGroupTrial(array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-'.uniqid(),
        'product_name' => 'Sample Product',
        'finish_good_code' => 'FG-001',
        'product_type' => 'Tube',
        'progress_status' => 'Approved',
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('trials.index', 'approved'));
    $response->assertRedirect(route('login'));
});

test('an unknown group segment 404s', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/trials/not-a-real-group')
        ->assertNotFound();
});

test('the approved group only lists approved trials', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeGroupTrial(['trial_code' => 'TRIAL-APPROVED', 'progress_status' => 'Approved']);
    makeGroupTrial(['trial_code' => 'TRIAL-IN-REVIEW', 'progress_status' => 'In Review']);

    $response = $this->actingAs($superAdmin)->get(route('trials.index', 'approved'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-APPROVED')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-IN-REVIEW')));
});

test('the rejected group includes trials rejected via final_decision', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeGroupTrial(['trial_code' => 'TRIAL-REJECTED-STATUS', 'progress_status' => 'Rejected']);
    makeGroupTrial(['trial_code' => 'TRIAL-REJECTED-DECISION', 'progress_status' => 'Approved', 'final_decision' => 'Rejected']);
    makeGroupTrial(['trial_code' => 'TRIAL-APPROVED', 'progress_status' => 'Approved']);

    $response = $this->actingAs($superAdmin)->get(route('trials.index', 'rejected'));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-REJECTED-STATUS')
            && collect($data)->pluck('trial_code')->contains('TRIAL-REJECTED-DECISION')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-APPROVED')));
});

test('the tracking group includes both In Review and Ready for Approval trials', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeGroupTrial(['trial_code' => 'TRIAL-READY', 'progress_status' => 'Ready for Approval']);
    makeGroupTrial(['trial_code' => 'TRIAL-IN-REVIEW', 'progress_status' => 'In Review']);
    makeGroupTrial(['trial_code' => 'TRIAL-APPROVED', 'progress_status' => 'Approved']);

    $response = $this->actingAs($superAdmin)->get(route('trials.index', 'tracking'));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-READY')
            && collect($data)->pluck('trial_code')->contains('TRIAL-IN-REVIEW')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-APPROVED')));
});

test('a staff member with no assigned approvals only sees their own In Review trials in the tracking group', function () {
    $staff = User::factory()->create(['role' => 'Staff', 'email' => 'staff@local.test']);
    makeGroupTrial(['trial_code' => 'TRIAL-MINE-IN-REVIEW', 'progress_status' => 'In Review', 'created_by' => 'staff@local.test']);
    makeGroupTrial(['trial_code' => 'TRIAL-READY-UNASSIGNED', 'progress_status' => 'Ready for Approval', 'created_by' => 'other@local.test']);

    $response = $this->actingAs($staff)->get(route('trials.index', 'tracking'));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-MINE-IN-REVIEW')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-READY-UNASSIGNED')));
});

test('the product_type filter narrows the trial list', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeGroupTrial(['trial_code' => 'TRIAL-TUBE', 'product_type' => 'Tube']);
    makeGroupTrial(['trial_code' => 'TRIAL-BOTTLE', 'product_type' => 'Bottle']);

    $response = $this->actingAs($superAdmin)->get(route('trials.index', ['group' => 'approved', 'product_type' => 'Tube']));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-TUBE')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-BOTTLE')));
});
