<?php

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('project_files');
});

function improvementPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Reduce changeover time',
        'department' => 'PROD',
        'type' => 'Process',
        'issue' => 'Changeover takes 90 minutes.',
        'background' => 'Manual cleaning.',
        'links' => "https://example.test/a\nhttps://example.test/b\n",
    ], $overrides);
}

function piUser(): User
{
    return User::factory()->create(['role' => 'Team Leader', 'department' => 'PI']);
}

test('gantt renders stages and milestones and filters by project and status', function () {
    $user = User::factory()->create(['role' => 'Viewer']);
    $a = Project::create(['name' => 'Alpha', 'status' => 'On Progress', 'pic_name' => 'Zahra', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30']);
    $b = Project::create(['name' => 'Beta', 'status' => 'Completed', 'pic_name' => 'Rozy']);
    ProjectStage::create(['project_id' => $a->id, 'name' => 'Design', 'status' => 'On Progress', 'plan_start' => '2026-01-01', 'plan_end' => '2026-02-01', 'progress' => 40, 'is_parallel' => true]);
    $a->milestones()->create(['name' => 'FAT', 'due_date' => '2026-03-01', 'status' => 'Pending']);
    Project::create(['name' => 'Gone', 'status' => 'On Progress', 'deleted_at' => now()]);

    $this->actingAs($user)->get(route('projects.gantt'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/gantt')
            ->has('projects', 2)
            ->where('projects.0.name', 'Alpha')
            ->where('projects.0.stages.0.is_parallel', true)
            ->where('projects.0.milestones.0.name', 'FAT'));

    $this->actingAs($user)->get(route('projects.gantt', ['status' => 'Completed']))
        ->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.id', $b->id));

    $this->actingAs($user)->get(route('projects.gantt', ['project' => $a->id]))
        ->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.id', $a->id));

    $this->actingAs($user)->get(route('projects.gantt', ['pic' => 'Rozy']))
        ->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.id', $b->id));
});

test('any non-viewer submits an improvement and a viewer cannot', function () {
    $contributor = User::factory()->create(['role' => 'Team Leader', 'department' => 'PROD']);

    $this->actingAs($contributor)->post(route('projects.improvements.store'), improvementPayload())
        ->assertRedirect();

    $improvement = Improvement::firstOrFail();
    expect($improvement->status)->toBe('Baru')
        ->and($improvement->decision)->toBe('Pending')
        ->and($improvement->submitted_by_user_id)->toBe($contributor->id)
        ->and($improvement->links)->toBe(['https://example.test/a', 'https://example.test/b']);
    expect(ActivityLog::where('module', 'IMPROVEMENT')->where('action', 'CREATE')->count())->toBe(1);

    $viewer = User::factory()->create(['role' => 'Viewer']);
    $this->actingAs($viewer)->post(route('projects.improvements.store'), improvementPayload())->assertForbidden();
    expect(Improvement::count())->toBe(1);

    $this->actingAs($contributor)->post(route('projects.improvements.store'), ['title' => ''])
        ->assertSessionHasErrors(['title', 'issue']);
});

test('a contributor sees only own improvements while PI and staff see all', function () {
    $owner = User::factory()->create(['role' => 'Team Leader']);
    $other = User::factory()->create(['role' => 'Team Leader']);
    $mine = Improvement::create(['title' => 'Mine', 'submitted_by_user_id' => $owner->id, 'status' => 'Baru', 'decision' => 'Pending']);
    $theirs = Improvement::create(['title' => 'Theirs', 'submitted_by_user_id' => $other->id, 'status' => 'Baru', 'decision' => 'Pending']);

    $this->actingAs($owner)->get(route('projects.improvements.index'))
        ->assertInertia(fn ($page) => $page->has('items', 1)->where('items.0.id', $mine->id));
    $this->actingAs($owner)->get(route('projects.improvements.show', $theirs))->assertForbidden();

    $this->actingAs(piUser())->get(route('projects.improvements.index'))
        ->assertInertia(fn ($page) => $page->has('items', 2));
    $this->actingAs(User::factory()->create(['role' => 'Staff']))->get(route('projects.improvements.show', $theirs))->assertOk();
});

test('the owner edits only while status is Baru', function () {
    $owner = User::factory()->create(['role' => 'Team Leader']);
    $improvement = Improvement::create(['title' => 'Old', 'submitted_by_user_id' => $owner->id, 'status' => 'Baru', 'decision' => 'Pending']);

    $this->actingAs($owner)->put(route('projects.improvements.update', $improvement), improvementPayload(['title' => 'New title']))
        ->assertRedirect();
    expect($improvement->fresh()->title)->toBe('New title');

    $improvement->update(['status' => 'Review PI']);
    $this->actingAs($owner)->put(route('projects.improvements.update', $improvement), improvementPayload(['title' => 'Late edit']))
        ->assertForbidden();
    expect($improvement->fresh()->title)->toBe('New title');
});

test('PI reviews with status decision and feedback and the owner cannot', function () {
    $owner = User::factory()->create(['role' => 'Team Leader']);
    $improvement = Improvement::create(['title' => 'X', 'submitted_by_user_id' => $owner->id, 'status' => 'Baru', 'decision' => 'Pending']);

    $this->actingAs($owner)->post(route('projects.improvements.review', $improvement), ['status' => 'Disetujui', 'decision' => 'Suggestion Only'])
        ->assertForbidden();

    $pi = piUser();
    $this->actingAs($pi)->post(route('projects.improvements.review', $improvement), [
        'status' => 'Menunggu Data',
        'decision' => 'Pending',
        'pi_feedback' => 'Need cycle time data',
    ])->assertRedirect();

    $improvement->refresh();
    expect($improvement->status)->toBe('Menunggu Data')
        ->and($improvement->pi_owner)->toBe($pi->name)
        ->and($improvement->feedback_log)->toHaveCount(1)
        ->and($improvement->feedback_log[0]['text'])->toBe('Need cycle time data');

    $this->actingAs($pi)->post(route('projects.improvements.review', $improvement), ['status' => 'Nope', 'decision' => 'Pending'])
        ->assertSessionHasErrors('status');
});

test('To Project creates a pre-filled project once and links it back', function () {
    $submitter = User::factory()->create(['role' => 'Staff']);
    $improvement = Improvement::create([
        'title' => 'Faster filling',
        'department' => 'PROD',
        'issue' => 'Slow line',
        'goals' => '+10% output',
        'submitted_by_user_id' => $submitter->id,
        'status' => 'Review PI',
        'decision' => 'Pending',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'Team Leader', 'department' => 'PROD']))
        ->post(route('projects.improvements.convert', $improvement))->assertForbidden();

    $pi = piUser();
    $this->actingAs($pi)->post(route('projects.improvements.convert', $improvement))
        ->assertRedirect();

    $improvement->refresh();
    $project = Project::findOrFail($improvement->project_id);
    expect($project->name)->toBe('Faster filling')
        ->and($project->category)->toBe('Improvement')
        ->and($project->pic_user_id)->toBe($submitter->id)
        ->and($project->description)->toContain('Slow line')
        ->and($improvement->decision)->toBe('To Project')
        ->and($improvement->status)->toBe('Disetujui');

    $this->actingAs($pi)->post(route('projects.improvements.convert', $improvement))
        ->assertSessionHasErrors('convert');
    expect(Project::count())->toBe(1);
});

test('improvement files upload download and delete with script and mime guards', function () {
    $owner = User::factory()->create(['role' => 'Team Leader']);
    $improvement = Improvement::create(['title' => 'F', 'submitted_by_user_id' => $owner->id, 'status' => 'Baru', 'decision' => 'Pending']);

    $this->actingAs($owner)->post(route('projects.improvements.files.store', $improvement), [
        'file' => UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf'),
        'description' => 'photo',
    ])->assertRedirect();

    $file = ImprovementFile::firstOrFail();
    Storage::disk('project_files')->assertExists($file->stored_name);

    $this->actingAs($owner)->get(route('projects.improvements.files.show', [$improvement, $file]))->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'Team Leader']))
        ->get(route('projects.improvements.files.show', [$improvement, $file]))->assertForbidden();

    $this->actingAs($owner)->post(route('projects.improvements.files.store', $improvement), [
        'file' => UploadedFile::fake()->create('shell.php', 1),
    ])->assertSessionHasErrors('file');
    expect(ImprovementFile::count())->toBe(1);

    $this->actingAs($owner)->delete(route('projects.improvements.files.destroy', [$improvement, $file]))->assertRedirect();
    expect(ImprovementFile::count())->toBe(0);
    Storage::disk('project_files')->assertMissing($file->stored_name);
});

test('only admin deletes an improvement (soft delete)', function () {
    $improvement = Improvement::create(['title' => 'D', 'status' => 'Baru', 'decision' => 'Pending']);

    $this->actingAs(piUser())->delete(route('projects.improvements.destroy', $improvement))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'Admin']))->delete(route('projects.improvements.destroy', $improvement))
        ->assertRedirect(route('projects.improvements.index'));

    expect($improvement->fresh()->deleted_at)->not->toBeNull();
    $this->actingAs(piUser())->get(route('projects.improvements.show', $improvement))->assertNotFound();
});

test('my work lists own projects and improvements and the PI review count', function () {
    $user = User::factory()->create(['role' => 'Staff', 'department' => 'PI']);
    Project::create(['name' => 'Mine', 'status' => 'On Progress', 'pic_user_id' => $user->id]);
    Project::create(['name' => 'Done', 'status' => 'Completed', 'pic_user_id' => $user->id]);
    Project::create(['name' => 'Other', 'status' => 'On Progress', 'pic_user_id' => User::factory()->create()->id]);
    Improvement::create(['title' => 'Mine open', 'submitted_by_user_id' => $user->id, 'status' => 'Baru', 'decision' => 'Pending']);
    Improvement::create(['title' => 'Mine done', 'submitted_by_user_id' => $user->id, 'status' => 'Disetujui', 'decision' => 'To Project']);
    Improvement::create(['title' => 'Someone else', 'status' => 'Review PI', 'decision' => 'Pending']);

    $this->actingAs($user)->get(route('my-work'))
        ->assertInertia(fn ($page) => $page
            ->where('myWork.myProjectsTotal', 1)
            ->where('myWork.myProjects.0.name', 'Mine')
            ->where('myWork.myImprovementsTotal', 1)
            ->where('myWork.canReviewImprovements', true)
            ->where('myWork.improvementsToReviewTotal', 2));
});

test('imported legacy history shows in the project detail', function () {
    $user = User::factory()->create(['role' => 'Viewer']);
    $project = Project::create(['name' => 'Legacy', 'status' => 'On Progress', 'legacy_id' => 'abc-123']);
    ActivityLog::create(['user_name' => 'Zahra', 'action' => 'LEGACY_LOG', 'module' => 'PROJECT', 'record_id' => 'legacy:log-1', 'record_label' => 'old note', 'new_data' => json_encode(['scope' => 'abc-123', 'text' => 'old note'])]);
    ActivityLog::create(['user_name' => 'Zahra', 'action' => 'LEGACY_LOG', 'module' => 'PROJECT', 'record_id' => 'legacy:log-2', 'record_label' => 'not mine', 'new_data' => json_encode(['scope' => 'abc-1234', 'text' => 'not mine'])]);

    $this->actingAs($user)->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->has('logs', 1)->where('logs.0.action', 'LEGACY_LOG'));
});

test('improvement lang group has id en ko parity', function () {
    $keys = fn (string $locale) => array_keys(Arr::dot(require lang_path("$locale/improvements.php")));

    expect($keys('en'))->toEqualCanonicalizing($keys('id'))
        ->and($keys('ko'))->toEqualCanonicalizing($keys('id'));
});
