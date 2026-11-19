<?php

use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Carbon;

function legacyCompatTrial(array $attrs = []): Trial
{
    $createdAt = $attrs['created_at'] ?? null;
    unset($attrs['created_at']);
    $trial = Trial::create(array_merge([
        'trial_code' => 'TRIAL-LEGACY-'.uniqid(),
        'product_name' => 'Sample',
        'product_type' => 'Tube',
        'progress_status' => 'Ready for Approval',
        'current_step' => 'Approval',
        'created_by' => 'owner@local.test',
        'batch_number' => 'B1',
        'bulk_code' => 'BC1',
        'support_team' => 'QA',
        'initiated_person_team' => 'Someone',
        'reason' => 'Testing',
        'bom' => 'BOM',
        'revision_no' => 0,
    ], $attrs));

    if ($createdAt) {
        DB::table('trials_header')->where('id', $trial->id)->update(['created_at' => $createdAt]);
    }

    return $trial->fresh();
}

beforeEach(function () {
    config(['trials.legacy_cutoff' => '2026-09-21 00:00:00', 'trials.auto_archive_days' => 365]);
});

test('an approver-less legacy trial can be approved and seen by Manager QAC', function () {
    $mgr = User::factory()->role('Manager QAC')->create();
    $trial = legacyCompatTrial(['created_at' => '2026-06-10 08:00:00']);

    expect($trial->fresh()->is_legacy)->toBeTrue();
    expect($mgr->can('approve', $trial))->toBeTrue();

    $this->actingAs($mgr)->get(route('approvals.index'))
        ->assertInertia(fn ($page) => $page->has('items.data', 1));
});

test('an approver-less NEW trial is still not approvable by Manager QAC', function () {
    $mgr = User::factory()->role('Manager QAC')->create();
    $trial = legacyCompatTrial(['created_at' => '2026-10-10 08:00:00']);

    expect($trial->fresh()->is_legacy)->toBeFalse();
    expect($mgr->can('approve', $trial))->toBeFalse();
});

test('auto-archive archives only finished trials decided over a year ago', function () {
    $old = legacyCompatTrial(['progress_status' => 'Approved', 'approved_at' => Carbon::now()->subDays(400)]);
    $recent = legacyCompatTrial(['progress_status' => 'Approved', 'approved_at' => Carbon::now()->subDays(30)]);
    $active = legacyCompatTrial(['progress_status' => 'In Review']);
    DB::table('trials_header')->where('id', $active->id)->update(['updated_at' => Carbon::now()->subDays(500)]);

    $this->artisan('trials:auto-archive --dry-run')->expectsOutputToContain('Would archive 1')->assertSuccessful();
    expect($old->fresh()->archived_at)->toBeNull();

    $this->artisan('trials:auto-archive')->expectsOutputToContain('Archived 1')->assertSuccessful();

    expect($old->fresh()->archived_at)->not->toBeNull()
        ->and($recent->fresh()->archived_at)->toBeNull()
        ->and($active->fresh()->archived_at)->toBeNull();
});
