<?php

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMilestone;
use App\Models\ProjectQuotation;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('project_files');
});

function projectPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Filling Machine Upgrade',
        'code' => 'PRJ-001',
        'category' => 'Machine',
        'status' => 'On Progress',
        'priority' => 'High',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'progress' => 10,
    ], $overrides);
}

function stagePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Design',
        'status' => 'On Progress',
        'progress' => 40,
    ], $overrides);
}

test('portfolio renders for any role and exposes create only to staff', function () {
    Project::create(['name' => 'A', 'status' => 'On Progress']);
    $viewer = User::factory()->create(['role' => 'Viewer']);
    $staff = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($viewer)->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/index')
            ->where('can.create', false)
            ->where('kpi.total', 1));

    $this->actingAs($staff)->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->where('can.create', true));
});

test('portfolio filters by status and search and hides soft-deleted projects', function () {
    Project::create(['name' => 'Alpha machine', 'status' => 'On Progress', 'category' => 'Machine']);
    Project::create(['name' => 'Beta tooling', 'status' => 'Completed', 'category' => 'Tooling']);
    Project::create(['name' => 'Gone', 'status' => 'On Progress', 'deleted_at' => now()]);
    $user = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($user)->get(route('projects.index', ['status' => 'Completed']))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1)->where('projects.data.0.name', 'Beta tooling'));

    $this->actingAs($user)->get(route('projects.index', ['q' => 'alpha']))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1)->where('projects.data.0.name', 'Alpha machine'));

    $this->actingAs($user)->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->where('kpi.total', 2));
});

test('attention card lists delayed, overdue and late-stage projects but not healthy ones', function () {
    $ok = Project::create(['name' => 'Healthy', 'status' => 'On Progress', 'end_date' => now()->addMonth()]);
    $delayed = Project::create(['name' => 'Delayed one', 'status' => 'Delayed']);
    $overdue = Project::create(['name' => 'Overdue one', 'status' => 'On Progress', 'end_date' => now()->subDay()]);
    $late = Project::create(['name' => 'Late stage one', 'status' => 'On Progress', 'end_date' => now()->addMonth()]);
    ProjectStage::create(['project_id' => $late->id, 'name' => 'S', 'status' => 'On Progress', 'plan_end' => now()->subDays(3)]);
    $user = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($user)->get(route('projects.index'))
        ->assertInertia(function ($page) {
            $names = collect($page->toArray()['props']['attention'])->pluck('name')->all();
            expect($names)->toContain('Delayed one', 'Overdue one', 'Late stage one')->not->toContain('Healthy');
        });
});

test('staff creates a project, it is logged, and the creator can update it', function () {
    $staff = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($staff)->post(route('projects.store'), projectPayload(['pic_user_id' => $staff->id]));

    $project = Project::firstWhere('code', 'PRJ-001');
    $response->assertRedirect(route('projects.show', $project));
    expect($project->pic_name)->toBe($staff->name)
        ->and($project->created_by_user_id)->toBe($staff->id);
    expect(ActivityLog::where('module', 'PROJECT')->where('action', 'CREATE')->where('record_id', (string) $project->id)->exists())->toBeTrue();

    $this->actingAs($staff)->put(route('projects.update', $project), projectPayload(['name' => 'Renamed', 'status' => 'Delayed']))
        ->assertRedirect();
    expect($project->fresh()->name)->toBe('Renamed')->and($project->fresh()->status)->toBe('Delayed');
    expect(ActivityLog::where('module', 'PROJECT')->where('action', 'UPDATE')->exists())->toBeTrue();
});

test('project validation rejects bad status, inverted dates and unknown PIC', function () {
    $staff = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($staff)->post(route('projects.store'), projectPayload([
        'status' => 'Bogus',
        'start_date' => '2026-05-01',
        'end_date' => '2026-01-01',
        'pic_user_id' => 99999,
        'name' => '',
    ]))->assertSessionHasErrors(['status', 'end_date', 'pic_user_id', 'name']);

    expect(Project::count())->toBe(0);
});

test('viewers cannot create and staff cannot touch a project they neither own nor lead', function () {
    $viewer = User::factory()->create(['role' => 'Viewer']);
    $staff = User::factory()->create(['role' => 'Staff']);
    $owner = User::factory()->create(['role' => 'Staff']);
    $project = Project::create(['name' => 'Foreign', 'pic_user_id' => $owner->id, 'created_by_user_id' => $owner->id]);

    $this->actingAs($viewer)->get(route('projects.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('projects.store'), projectPayload())->assertForbidden();

    $this->actingAs($staff)->get(route('projects.show', $project))->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.update', false));
    $this->actingAs($staff)->get(route('projects.edit', $project))->assertForbidden();
    $this->actingAs($staff)->put(route('projects.update', $project), projectPayload())->assertForbidden();
    $this->actingAs($staff)->post(route('projects.stages.store', $project), stagePayload())->assertForbidden();
    $this->actingAs($staff)->post(route('projects.milestones.store', $project), ['name' => 'M', 'status' => 'Pending'])->assertForbidden();
    $this->actingAs($staff)->delete(route('projects.destroy', $project))->assertForbidden();
});

test('only admin can delete and it is a soft delete', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $project = Project::create(['name' => 'Bye']);

    $this->actingAs($admin)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

    expect($project->fresh()->deleted_at)->not->toBeNull()->and($project->fresh()->deleted_by)->toBe($admin->id);
    $this->actingAs($admin)->get(route('projects.show', $project))->assertNotFound();
});

test('stages: create, update, delete keep project progress in sync and detect validation type', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id, 'progress' => 0]);

    $this->actingAs($staff)->post(route('projects.stages.store', $project), stagePayload(['name' => 'Trial Validasi', 'progress' => 100]))->assertRedirect();
    $this->actingAs($staff)->post(route('projects.stages.store', $project), stagePayload(['name' => 'Design', 'progress' => 0, 'status' => 'Not Started']))->assertRedirect();

    $validation = ProjectStage::firstWhere('name', 'Trial Validasi');
    $design = ProjectStage::firstWhere('name', 'Design');
    expect($validation->stage_type)->toBe('Validation')
        ->and($validation->status)->toBe('Completed')
        ->and($design->stage_type)->toBe('General')
        ->and($design->sort_order)->toBeGreaterThan($validation->sort_order)
        ->and($project->fresh()->progress)->toBe(50);

    $this->actingAs($staff)->put(route('projects.stages.update', [$project, $design]), stagePayload(['name' => 'Design', 'status' => 'Completed', 'progress' => 10]))->assertRedirect();
    expect($design->fresh()->progress)->toBe(100)->and($project->fresh()->progress)->toBe(100);

    $this->actingAs($staff)->delete(route('projects.stages.destroy', [$project, $design]))->assertRedirect();
    expect(ProjectStage::count())->toBe(1);
    expect(ActivityLog::where('module', 'PROJECT')->where('action', 'DELETE_STAGE')->exists())->toBeTrue();
});

test('stage validation: end before start, bad progress, wrong project', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id]);
    $other = Project::create(['name' => 'Other', 'pic_user_id' => $staff->id]);
    $stage = ProjectStage::create(['project_id' => $other->id, 'name' => 'x']);

    $this->actingAs($staff)->post(route('projects.stages.store', $project), stagePayload([
        'plan_start' => '2026-05-01', 'plan_end' => '2026-01-01', 'progress' => 150,
    ]))->assertSessionHasErrors(['plan_end', 'progress']);

    // A stage id from another project must not be editable through this project.
    $this->actingAs($staff)->put(route('projects.stages.update', [$project, $stage]), stagePayload())->assertNotFound();
});

test('milestones can be created, updated and deleted', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id]);

    $this->actingAs($staff)->post(route('projects.milestones.store', $project), ['name' => 'FAT', 'status' => 'Pending', 'due_date' => '2026-06-01'])->assertRedirect();
    $milestone = ProjectMilestone::firstWhere('name', 'FAT');

    $this->actingAs($staff)->put(route('projects.milestones.update', [$project, $milestone]), ['name' => 'FAT', 'status' => 'Done'])->assertRedirect();
    expect($milestone->fresh()->status)->toBe('Done');

    $this->actingAs($staff)->post(route('projects.milestones.store', $project), ['name' => 'X', 'status' => 'Nope'])->assertSessionHasErrors('status');

    $this->actingAs($staff)->delete(route('projects.milestones.destroy', [$project, $milestone]))->assertRedirect();
    expect(ProjectMilestone::count())->toBe(0);
});

test('quotations CRUD and the cross-project list with filters', function () {
    $staff = User::factory()->create(['role' => 'Staff', 'department' => 'PIE']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id]);
    $payload = ['vendor_name' => 'Vendor A', 'quotation_no' => 'Q-1', 'amount' => 1500000, 'currency' => 'idr', 'status' => 'Review'];

    $this->actingAs($staff)->post(route('projects.quotations.store', $project), $payload)->assertRedirect();
    $this->actingAs($staff)->post(route('projects.quotations.store', $project), array_merge($payload, ['vendor_name' => 'Vendor B', 'status' => 'Terpilih']))->assertRedirect();

    $quotation = ProjectQuotation::firstWhere('vendor_name', 'Vendor A');
    expect($quotation->currency)->toBe('IDR')->and((float) $quotation->amount)->toBe(1500000.0);

    $this->actingAs($staff)->put(route('projects.quotations.update', [$project, $quotation]), array_merge($payload, ['status' => 'Diterima']))->assertRedirect();
    expect($quotation->fresh()->status)->toBe('Diterima');

    $this->actingAs($staff)->get(route('projects.quotations.index', ['status' => 'Terpilih']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.vendor_name', 'Vendor B'));

    $this->actingAs($staff)->post(route('projects.quotations.store', $project), array_merge($payload, ['link' => 'not a url']))->assertSessionHasErrors('link');

    $this->actingAs($staff)->delete(route('projects.quotations.destroy', [$project, $quotation]))->assertRedirect();
    expect(ProjectQuotation::count())->toBe(1);
});

test('Finance (quotations, quotation files and the project page sections) is Admin and PIE only', function () {
    $pie = User::factory()->create(['role' => 'Staff', 'department' => 'PIE']);
    $other = User::factory()->create(['role' => 'Staff', 'department' => 'QAC']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $other->id]);
    $quotation = ProjectQuotation::create(['project_id' => $project->id, 'vendor_name' => 'V']);
    $payload = ['vendor_name' => 'Vendor A', 'status' => 'Review', 'currency' => 'idr'];

    // Even the project's own PIC from another department cannot touch Finance data.
    $this->actingAs($other)->get(route('projects.quotations.index'))->assertForbidden();
    $this->actingAs($other)->post(route('projects.quotations.store', $project), $payload)->assertForbidden();
    $this->actingAs($other)->put(route('projects.quotations.update', [$project, $quotation]), $payload)->assertForbidden();
    $this->actingAs($other)->delete(route('projects.quotations.destroy', [$project, $quotation]))->assertForbidden();
    $this->actingAs($other)->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->has('quotations', 0)->has('purchaseOrders', 0)->where('can.finance', false));

    $this->actingAs($pie)->get(route('projects.quotations.index'))->assertOk();
    $this->actingAs($pie)->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->has('quotations', 1)->where('can.finance', true));
});

test('files: upload to project/stage/quotation, authorized download, delete removes the physical file', function () {
    $staff = User::factory()->create(['role' => 'Staff', 'department' => 'PIE']);
    $viewer = User::factory()->create(['role' => 'Viewer']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id]);
    $stage = ProjectStage::create(['project_id' => $project->id, 'name' => 'S']);
    $quotation = ProjectQuotation::create(['project_id' => $project->id, 'vendor_name' => 'V']);

    $pdf = fn (string $name = 'spec.pdf') => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $this->actingAs($staff)->post(route('projects.files.store', $project), ['context' => 'project', 'file' => $pdf()])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($staff)->post(route('projects.files.store', $project), ['context' => 'stage', 'stage_id' => $stage->id, 'file' => $pdf('stage.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($staff)->post(route('projects.files.store', $project), ['context' => 'quotation', 'quotation_id' => $quotation->id, 'file' => $pdf('quote.pdf')])->assertSessionHasNoErrors();

    expect(ProjectFile::count())->toBe(3)
        ->and(ProjectFile::firstWhere('context', 'stage')->stage_id)->toBe($stage->id)
        ->and($quotation->fresh()->file_id)->toBe(ProjectFile::firstWhere('context', 'quotation')->id);

    $file = ProjectFile::firstWhere('context', 'project');
    Storage::disk('project_files')->assertExists($file->stored_name);

    // Never a public path: stored name is random, download goes through the route.
    expect($file->stored_name)->toMatch('/^[a-f0-9]{32}\.pdf$/');
    $this->actingAs($viewer)->get(route('projects.files.show', [$project, $file]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    auth()->logout();
    $this->get(route('projects.files.show', [$project, $file]))->assertRedirect(route('login'));

    // A viewer cannot upload or delete.
    $this->actingAs($viewer)->post(route('projects.files.store', $project), ['context' => 'project', 'file' => $pdf()])->assertForbidden();
    $this->actingAs($viewer)->delete(route('projects.files.destroy', [$project, $file]))->assertForbidden();

    $this->actingAs($staff)->delete(route('projects.files.destroy', [$project, $file]))->assertRedirect();
    Storage::disk('project_files')->assertMissing($file->stored_name);
    expect(ProjectFile::find($file->id))->toBeNull();
});

test('replacing a quotation file removes the previous one', function () {
    $staff = User::factory()->create(['role' => 'Staff', 'department' => 'PIE']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id]);
    $quotation = ProjectQuotation::create(['project_id' => $project->id, 'vendor_name' => 'V']);
    $png = fn () => UploadedFile::fake()->image('q.png');

    $this->actingAs($staff)->post(route('projects.files.store', $project), ['context' => 'quotation', 'quotation_id' => $quotation->id, 'file' => $png()]);
    $first = ProjectFile::first();
    $this->actingAs($staff)->post(route('projects.files.store', $project), ['context' => 'quotation', 'quotation_id' => $quotation->id, 'file' => $png()]);

    expect(ProjectFile::count())->toBe(1)->and($quotation->fresh()->file_id)->not->toBe($first->id);
    Storage::disk('project_files')->assertMissing($first->stored_name);
});

test('upload refuses script names, non-whitelisted types, mismatched content and foreign targets', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id]);
    $other = Project::create(['name' => 'Other', 'pic_user_id' => $staff->id]);
    $foreignStage = ProjectStage::create(['project_id' => $other->id, 'name' => 'S']);

    $send = fn (array $extra) => $this->actingAs($staff)->post(route('projects.files.store', $project), $extra + ['context' => 'project']);

    // double extension that hides a script
    $send(['file' => UploadedFile::fake()->createWithContent('shell.php.png', '<?php echo 1;')])->assertSessionHasErrors('file');
    // plain script and html, executable
    $send(['file' => UploadedFile::fake()->createWithContent('run.php', '<?php echo 1;')])->assertSessionHasErrors('file');
    $send(['file' => UploadedFile::fake()->createWithContent('page.html', '<script>1</script>')])->assertSessionHasErrors('file');
    $send(['file' => UploadedFile::fake()->createWithContent('tool.exe', 'MZ')])->assertSessionHasErrors('file');
    // content does not match its .png extension
    // (a real temp file: the fake() helper guesses MIME from the name, not the bytes)
    $tmp = tempnam(sys_get_temp_dir(), 'pm');
    file_put_contents($tmp, '<?php echo "not an image";');
    $send(['file' => new UploadedFile($tmp, 'fake.png', null, null, true)])->assertSessionHasErrors('file');
    // stage from another project
    $send(['context' => 'stage', 'stage_id' => $foreignStage->id, 'file' => UploadedFile::fake()->image('a.png')])->assertSessionHasErrors('stage_id');

    expect(ProjectFile::count())->toBe(0);
    expect(Storage::disk('project_files')->allFiles())->toBeEmpty();
});

test('missing physical files return 404 instead of an error', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $project = Project::create(['name' => 'P', 'pic_user_id' => $staff->id]);
    $file = ProjectFile::create(['project_id' => $project->id, 'file_name' => 'gone.pdf', 'mime_type' => 'application/pdf', 'stored_name' => 'gone.pdf', 'is_missing' => true]);

    $this->actingAs($staff)->get(route('projects.files.show', [$project, $file]))->assertNotFound();
});

test('detail page lists children and the project activity history', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $this->actingAs($staff)->post(route('projects.store'), projectPayload(['pic_user_id' => $staff->id]));
    $project = Project::first();
    $this->actingAs($staff)->post(route('projects.stages.store', $project), stagePayload());
    $this->actingAs($staff)->post(route('projects.milestones.store', $project), ['name' => 'M', 'status' => 'Pending']);

    $this->actingAs($staff)->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/show')
            ->has('stages', 1)
            ->has('milestones', 1)
            ->where('can.update', true)
            ->where('can.delete', false)
            ->has('logs', 3));
});

test('every projects translation key exists in all three locales', function () {
    $keys = [];
    foreach (['id', 'en', 'ko'] as $locale) {
        $keys[$locale] = array_keys(Arr::dot(require lang_path("$locale/projects.php")));
        sort($keys[$locale]);
    }
    expect($keys['en'])->toBe($keys['id'])->and($keys['ko'])->toBe($keys['id']);
});
