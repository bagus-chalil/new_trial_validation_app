<?php

use App\Models\ActivityLog;
use App\Models\MasterOption;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Trial;
use App\Models\User;

/*
 * Projects module Fase 4: Validation stages linked to Trials.
 */

function p4Product(string $name = 'Cream Tube 50ml'): Product
{
    $product = Product::create(['product_name' => $name, 'finish_good_code' => 'FG-P4']);
    $product->is_active = true;
    $product->save();

    return $product;
}

function p4SeedOptions(): MasterOption
{
    foreach ([
        'product_type' => ['Tube'],
        'validation_category' => ['New Product'],
        'validation_scope' => ['Retail'],
    ] as $type => $names) {
        foreach ($names as $name) {
            MasterOption::create(['type' => $type, 'name' => $name, 'sort_order' => 0, 'is_active' => true]);
        }
    }

    MasterOption::create(['type' => 'machine_used', 'name' => 'Line B', 'sort_order' => 1, 'is_active' => true]);

    return MasterOption::create(['type' => 'machine_used', 'name' => 'Line A', 'sort_order' => 0, 'is_active' => true]);
}

function p4TrialPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'product_id' => $product->id,
        'product_type' => 'Tube',
        'validation_date' => '2026-11-20',
        'validation_category' => 'New Product',
        'risk_level' => 'Low',
        'validation_scope' => ['Retail'],
        'machine_used' => ['Line A'],
        'estimate_qty' => 100,
        'batch_number' => 'B-1',
        'bulk_code' => 'BULK-1',
        'support_team' => 'PIE',
        'initiated_person_team' => 'Zahra',
        'reason' => 'New machine validation',
        'bom' => 'BOM',
    ], $overrides);
}

function p4Project(User $pic, array $attributes = []): Project
{
    return Project::create(array_merge([
        'name' => 'New filling machine',
        'code' => 'PRJ-1',
        'status' => 'On Progress',
        'pic_user_id' => $pic->id,
        'pic_name' => $pic->name,
        'created_by_user_id' => $pic->id,
    ], $attributes));
}

function p4Stage(Project $project, array $attributes = []): ProjectStage
{
    return ProjectStage::create(array_merge([
        'project_id' => $project->id,
        'name' => 'Validasi',
        'stage_type' => 'Validation',
        'status' => 'Not Started',
        'progress' => 0,
    ], $attributes));
}

function p4Trial(array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-P4-'.uniqid(),
        'product_name' => 'Cream Tube 50ml',
        'product_type' => 'Tube',
        'progress_status' => 'Draft',
        'current_step' => 'Validation',
        'created_by' => 'owner@local.test',
        'batch_number' => 'B1',
        'bulk_code' => 'BC1',
        'support_team' => 'QA',
        'initiated_person_team' => 'Someone',
        'reason' => 'Testing',
        'bom' => 'BOM',
        'revision_no' => 0,
    ], $attributes));
}

test('the project form stores machine and product links and the detail page shows them', function () {
    $staff = User::factory()->role('Staff')->create();
    $machine = p4SeedOptions();
    $product = p4Product();

    $this->actingAs($staff)->post(route('projects.store'), [
        'name' => 'Line A upgrade',
        'category' => 'Machine',
        'status' => 'On Progress',
        'priority' => 'High',
        'pic_user_id' => $staff->id,
        'machine_option_id' => $machine->id,
        'product_id' => $product->id,
    ])->assertRedirect();

    $project = Project::where('name', 'Line A upgrade')->firstOrFail();
    expect($project->machine_option_id)->toBe($machine->id);
    expect($project->product_id)->toBe($product->id);

    $this->actingAs($staff)->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('project.machine_name', 'Line A')
            ->where('project.product_name', 'Cream Tube 50ml')
            ->where('can.createTrial', true));
});

test('a machine that is not a machine_used option is rejected', function () {
    $staff = User::factory()->role('Staff')->create();
    p4SeedOptions();
    $notMachine = MasterOption::where('type', 'product_type')->firstOrFail();

    $this->actingAs($staff)->post(route('projects.store'), [
        'name' => 'X',
        'category' => 'Machine',
        'status' => 'On Progress',
        'priority' => 'High',
        'machine_option_id' => $notMachine->id,
    ])->assertSessionHasErrors('machine_option_id');
});

test('the trial form opened from a Validation stage is pre-filled from the project', function () {
    $staff = User::factory()->role('Staff')->create();
    $machine = p4SeedOptions();
    $product = p4Product();
    $project = p4Project($staff, ['machine_option_id' => $machine->id, 'product_id' => $product->id]);
    $stage = p4Stage($project);

    $this->actingAs($staff)->get(route('trials.create', ['project_stage' => $stage->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('trials/form')
            ->where('projectStage.id', $stage->id)
            ->where('projectStage.project_name', 'New filling machine')
            ->where('prefill.product_id', $product->id)
            ->where('prefill.machine_used', ['Line A']));
});

test('creating a draft trial from a stage links it and syncs the stage', function () {
    $staff = User::factory()->role('Staff')->create();
    p4SeedOptions();
    $product = p4Product();
    $project = p4Project($staff);
    $stage = p4Stage($project);

    $response = $this->actingAs($staff)->post(route('trials.store'), p4TrialPayload($product, ['project_stage_id' => $stage->id]));

    $trial = Trial::query()->latest('id')->firstOrFail();
    $response->assertRedirect(route('trials.validation.edit', $trial));

    $stage->refresh();
    expect($stage->trial_id)->toBe($trial->id);
    expect($stage->status)->toBe('On Progress');
    expect($stage->progress)->toBe(20);
    expect($stage->actual_start)->not->toBeNull();
    expect($project->fresh()->progress)->toBe(20);
    expect(ActivityLog::where('module', 'PROJECT')->where('action', 'LINK_TRIAL')->exists())->toBeTrue();
});

test('a draft trial cannot be created from a General stage, an already linked stage, or a stranger\'s project', function () {
    $staff = User::factory()->role('Staff')->create();
    $stranger = User::factory()->role('Staff')->create();
    p4SeedOptions();
    $product = p4Product();
    $project = p4Project($staff);
    $general = p4Stage($project, ['name' => 'Installation', 'stage_type' => 'General']);
    $linked = p4Stage($project, ['trial_id' => p4Trial()->id]);
    $free = p4Stage($project);
    $before = Trial::count();

    $this->actingAs($staff)->post(route('trials.store'), p4TrialPayload($product, ['project_stage_id' => $general->id]))
        ->assertSessionHasErrors('project_stage_id');
    $this->actingAs($staff)->post(route('trials.store'), p4TrialPayload($product, ['project_stage_id' => $linked->id]))
        ->assertSessionHasErrors('project_stage_id');
    $this->actingAs($stranger)->post(route('trials.store'), p4TrialPayload($product, ['project_stage_id' => $free->id]))
        ->assertForbidden();

    expect(Trial::count())->toBe($before);
    expect($free->fresh()->trial_id)->toBeNull();
});

test('an existing trial can be linked and unlinked, and one trial only belongs to one stage', function () {
    $staff = User::factory()->role('Staff')->create();
    $project = p4Project($staff);
    $stage = p4Stage($project);
    $other = p4Stage($project, ['name' => 'Trial 2']);
    $trial = p4Trial(['progress_status' => 'In Review', 'current_step' => 'Review']);

    $this->actingAs($staff)->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('linkableTrials.0.value', (string) $trial->id));

    $this->actingAs($staff)->post(route('projects.stages.trial.store', [$project, $stage]), ['trial_id' => $trial->id])
        ->assertRedirect(route('projects.show', $project));

    $stage->refresh();
    expect($stage->trial_id)->toBe($trial->id);
    expect($stage->progress)->toBe(60);

    $this->actingAs($staff)->post(route('projects.stages.trial.store', [$project, $other]), ['trial_id' => $trial->id])
        ->assertSessionHasErrors('trial_id');

    $this->actingAs($staff)->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('stages.0.trial.trial_code', $trial->trial_code)
            ->where('stages.0.trial.progress_status', 'In Review')
            ->where('linkableTrials', []));

    $this->actingAs($staff)->delete(route('projects.stages.trial.destroy', [$project, $stage]))
        ->assertRedirect(route('projects.show', $project));

    expect($stage->fresh()->trial_id)->toBeNull();
    expect(ActivityLog::where('action', 'UNLINK_TRIAL')->exists())->toBeTrue();
});

test('a viewer cannot link a trial to a stage', function () {
    $pic = User::factory()->role('Staff')->create();
    $viewer = User::factory()->role('Viewer')->create();
    $project = p4Project($pic);
    $stage = p4Stage($project);
    $trial = p4Trial(['progress_status' => 'In Review']);

    $this->actingAs($viewer)->post(route('projects.stages.trial.store', [$project, $stage]), ['trial_id' => $trial->id])
        ->assertForbidden();

    expect($stage->fresh()->trial_id)->toBeNull();
});

test('approving a linked trial completes the stage, recalculates the project and notifies the PIC', function () {
    $pic = User::factory()->role('Staff')->create();
    $admin = User::factory()->role('Admin')->create();
    $project = p4Project($pic);
    $trial = p4Trial(['progress_status' => 'Ready for Approval', 'current_step' => 'Approval']);
    $stage = p4Stage($project, ['trial_id' => $trial->id, 'status' => 'On Progress', 'progress' => 80]);
    p4Stage($project, ['name' => 'Commissioning', 'stage_type' => 'General', 'progress' => 0]);

    $this->actingAs($admin)->post(route('approvals.update', $trial), [
        'decision' => 'Approved',
        'approval_comment' => 'OK',
        'signature_password' => 'password',
    ])->assertRedirect();

    $stage->refresh();
    expect($stage->status)->toBe('Completed');
    expect($stage->progress)->toBe(100);
    expect($stage->actual_end)->not->toBeNull();
    expect($project->fresh()->progress)->toBe(50);
    expect(ActivityLog::where('action', 'SYNC_TRIAL_STAGE')->where('record_id', (string) $project->id)->exists())->toBeTrue();
    expect(Notification::where('user_id', $pic->id)->where('type', 'project')->where('trial_id', $trial->id)->exists())->toBeTrue();
});

test('a rejected linked trial leaves the stage status alone', function () {
    $pic = User::factory()->role('Staff')->create();
    $admin = User::factory()->role('Admin')->create();
    $project = p4Project($pic);
    $trial = p4Trial(['progress_status' => 'Ready for Approval', 'current_step' => 'Approval']);
    $stage = p4Stage($project, ['trial_id' => $trial->id, 'status' => 'On Progress', 'progress' => 80]);

    $this->actingAs($admin)->post(route('approvals.update', $trial), [
        'decision' => 'Rejected',
        'approval_comment' => 'Not good',
        'signature_password' => 'password',
    ])->assertRedirect();

    $stage->refresh();
    expect($stage->status)->toBe('On Progress');
    expect($stage->progress)->toBe(80);
    // The PIC still hears about it.
    expect(Notification::where('user_id', $pic->id)->where('type', 'project')->exists())->toBeTrue();
});

test('the trial report links back to its project stage', function () {
    $admin = User::factory()->role('Admin')->create();
    $project = p4Project($admin);
    $trial = p4Trial(['progress_status' => 'In Review']);
    p4Stage($project, ['trial_id' => $trial->id]);

    $this->actingAs($admin)->get(route('trials.report.show', $trial))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projectLinks.0.project_id', $project->id)
            ->where('projectLinks.0.stage_name', 'Validasi'));
});

test('projects:sync-trial-stages catches up on status changes made outside this app', function () {
    $pic = User::factory()->role('Staff')->create();
    $project = p4Project($pic);
    $trial = p4Trial(['progress_status' => 'Draft']);
    $stage = p4Stage($project, ['trial_id' => $trial->id]);

    // e.g. the legacy app approved it directly in trials_header.
    $trial->forceFill(['progress_status' => 'Approved', 'approved_at' => now()])->save();

    $this->artisan('projects:sync-trial-stages', ['--dry-run' => true])->assertSuccessful();
    expect($stage->fresh()->status)->toBe('Not Started');

    $this->artisan('projects:sync-trial-stages')->assertSuccessful();
    expect($stage->fresh()->status)->toBe('Completed');
    expect(Notification::where('type', 'project')->exists())->toBeFalse();
});
