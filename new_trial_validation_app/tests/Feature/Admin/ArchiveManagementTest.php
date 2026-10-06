<?php

use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Carbon;

function makeArchivableTrial(array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-'.uniqid(),
        'product_name' => 'Sample Product',
        'finish_good_code' => 'FG-001',
        'product_type' => 'Tube',
        'progress_status' => 'Approved',
        'final_decision' => 'Approved',
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));
}

function makeArchivedTrial(array $attributes = [], ?User $archivedBy = null): Trial
{
    $trial = makeArchivableTrial($attributes);
    $trial->archived_at = Carbon::now();
    $trial->archived_by = $archivedBy?->id;
    $trial->save();

    return $trial;
}

test('non-admin cannot view the archive list', function () {
    $staff = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($staff)
        ->get(route('admin.archive.index'))
        ->assertForbidden();
});

test('admin can view the archive list', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    makeArchivedTrial(['trial_code' => 'TRIAL-ARCHIVED-1']);

    $response = $this->actingAs($admin)->get(route('admin.archive.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-ARCHIVED-1')));
});

test('non-archived trials are excluded from the archive list', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    makeArchivableTrial(['trial_code' => 'TRIAL-NOT-ARCHIVED']);

    $response = $this->actingAs($admin)->get(route('admin.archive.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => ! collect($data)->pluck('trial_code')->contains('TRIAL-NOT-ARCHIVED')));
});

test('the q filter searches trial code, product name, and product type', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    makeArchivedTrial(['trial_code' => 'TRIAL-MATCH', 'product_name' => 'Other Product']);
    makeArchivedTrial(['trial_code' => 'TRIAL-OTHER', 'product_name' => 'Different Product']);

    $response = $this->actingAs($admin)->get(route('admin.archive.index', ['q' => 'MATCH']));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-MATCH')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-OTHER')));
});

test('admin can archive an approved trial', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $trial = makeArchivableTrial(['trial_code' => 'TRIAL-TO-ARCHIVE']);

    $this->actingAs($admin)
        ->post(route('admin.archive.store', $trial))
        ->assertRedirect();

    $trial->refresh();
    expect($trial->archived_at)->not->toBeNull();
    expect($trial->archived_by)->toBe($admin->id);
});

test('admin cannot archive a trial that is still in progress', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $trial = makeArchivableTrial(['trial_code' => 'TRIAL-DRAFT', 'progress_status' => 'Draft', 'final_decision' => null]);

    $this->actingAs($admin)
        ->post(route('admin.archive.store', $trial))
        ->assertForbidden();

    expect($trial->refresh()->archived_at)->toBeNull();
});

test('staff cannot archive a trial', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $trial = makeArchivableTrial();

    $this->actingAs($staff)
        ->post(route('admin.archive.store', $trial))
        ->assertForbidden();

    expect($trial->refresh()->archived_at)->toBeNull();
});

test('admin can unarchive a trial', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $trial = makeArchivedTrial(['trial_code' => 'TRIAL-TO-UNARCHIVE']);

    $this->actingAs($admin)
        ->delete(route('admin.archive.destroy', $trial))
        ->assertRedirect(route('admin.archive.index'));

    $trial->refresh();
    expect($trial->archived_at)->toBeNull();
    expect($trial->archived_by)->toBeNull();
});

test('staff cannot unarchive a trial', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $trial = makeArchivedTrial();

    $this->actingAs($staff)
        ->delete(route('admin.archive.destroy', $trial))
        ->assertForbidden();

    expect($trial->refresh()->archived_at)->not->toBeNull();
});

test('an archived trial is hidden from the normal approved trials list', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    makeArchivedTrial(['trial_code' => 'TRIAL-HIDDEN']);
    makeArchivableTrial(['trial_code' => 'TRIAL-VISIBLE']);

    $response = $this->actingAs($admin)->get(route('trials.index', 'approved'));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-VISIBLE')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-HIDDEN')));
});
