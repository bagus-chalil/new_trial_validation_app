<?php

use App\Models\Improvement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

function pmUser(string $role, array $extra = []): User
{
    return User::factory()->create(['role' => $role] + $extra);
}

test('manage-projects gate is admin only', function () {
    expect(Gate::forUser(pmUser('Admin'))->allows('manage-projects'))->toBeTrue()
        ->and(Gate::forUser(pmUser('Staff'))->allows('manage-projects'))->toBeFalse()
        ->and(Gate::forUser(pmUser('Viewer'))->allows('manage-projects'))->toBeFalse();
});

test('project policy: staff change only their own projects, admin all, viewer none', function () {
    $staff = pmUser('Staff');
    $other = pmUser('Staff');
    $admin = pmUser('Admin');
    $viewer = pmUser('Viewer');

    $asPic = Project::create(['name' => 'A', 'pic_user_id' => $staff->id]);
    $asCreator = Project::create(['name' => 'B', 'created_by_user_id' => $staff->id]);
    $foreign = Project::create(['name' => 'C', 'pic_user_id' => $other->id]);

    expect($staff->can('create', Project::class))->toBeTrue()
        ->and($viewer->can('create', Project::class))->toBeFalse()
        ->and($staff->can('update', $asPic))->toBeTrue()
        ->and($staff->can('update', $asCreator))->toBeTrue()
        ->and($staff->can('update', $foreign))->toBeFalse()
        ->and($viewer->can('update', $asPic))->toBeFalse()
        ->and($admin->can('update', $foreign))->toBeTrue()
        ->and($staff->can('delete', $asPic))->toBeFalse()
        ->and($admin->can('delete', $asPic))->toBeTrue()
        ->and($viewer->can('view', $foreign))->toBeTrue();
});

test('improvement policy: owners edit their own until review, PI reviews, viewers cannot submit', function () {
    $owner = pmUser('Staff');
    $stranger = pmUser('Viewer');
    $pi = pmUser('Staff', ['department' => 'PIE']);
    $admin = pmUser('Admin');
    $plainStaff = pmUser('Staff', ['department' => 'QAC']);

    $fresh = Improvement::create(['title' => 'X', 'submitted_by_user_id' => $owner->id, 'status' => 'Baru']);
    $inReview = Improvement::create(['title' => 'Y', 'submitted_by_user_id' => $owner->id, 'status' => 'Review PI']);

    expect($owner->can('create', Improvement::class))->toBeTrue()
        ->and($stranger->can('create', Improvement::class))->toBeFalse()
        ->and($owner->can('update', $fresh))->toBeTrue()
        ->and($owner->can('update', $inReview))->toBeFalse()
        ->and($stranger->can('view', $fresh))->toBeFalse()
        ->and($owner->can('view', $fresh))->toBeTrue()
        ->and($pi->can('review', $fresh))->toBeTrue()
        ->and($admin->can('review', $fresh))->toBeTrue()
        ->and($plainStaff->can('review', $fresh))->toBeFalse()
        ->and($owner->can('review', $fresh))->toBeFalse()
        ->and($plainStaff->can('delete', $fresh))->toBeFalse()
        ->and($admin->can('delete', $fresh))->toBeTrue();
});
