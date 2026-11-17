<?php

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\MasterOption;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectQuotation;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Builds a throw-away sqlite "project_tracker" source DB on its own connection. */
function pmLegacySource(): string
{
    config(['database.connections.legacy_src' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
    DB::purge('legacy_src');
    $s = Schema::connection('legacy_src');

    $s->create('projects', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('code')->default('');
        $t->string('name');
        $t->string('category')->default('General');
        $t->string('status')->default('On Progress');
        $t->string('priority')->default('Medium');
        $t->string('pic')->default('');
        $t->string('area')->default('');
        $t->date('start_date')->nullable();
        $t->date('end_date')->nullable();
        $t->integer('progress')->default(0);
        $t->text('description')->nullable();
        $t->string('vendor')->default('');
        $t->decimal('budget', 18, 2)->default(0);
        $t->decimal('actual', 18, 2)->default(0);
        $t->string('po')->default('');
        $t->text('metadata')->nullable();
        $t->string('updated_at')->nullable();
        $t->string('updated_by')->default('');
    });
    $s->create('project_stages', fn (Blueprint $t) => $t->string('id')->primary());
    $s->create('project_quotations', fn (Blueprint $t) => $t->string('id')->primary());
    $s->create('project_milestones', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('project_id');
        $t->string('name');
        $t->date('due_date')->nullable();
        $t->string('status')->default('Pending');
        $t->text('note')->nullable();
    });
    $s->create('project_files', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('project_id')->nullable();
        $t->string('file_name');
        $t->string('mime_type');
        $t->integer('size_bytes')->default(0);
        $t->string('stored_name');
        $t->string('uploaded_at')->nullable();
    });
    $s->create('project_logs', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('project_id');
        $t->string('actor')->default('');
        $t->text('action');
        $t->string('created_at')->nullable();
    });
    $s->create('settings', function (Blueprint $t) {
        $t->string('key_name')->primary();
        $t->text('value_json')->nullable();
    });

    $src = DB::connection('legacy_src');
    $src->table('projects')->insert(
        [
            'id' => 'proj-a', 'code' => 'PRJ-001', 'name' => 'Sealing Machine', 'category' => 'Machine',
            'pic' => 'Zahra Pic', 'updated_by' => 'Zahra Pic', 'progress' => 50, 'start_date' => '2026-01-01',
            'updated_at' => '2026-11-01 10:00:00',
            'metadata' => json_encode([
                'createdBy' => 'Unknown Person',
                'log' => [['t' => 1, 'by' => 'x', 'items' => ['a']]],
                'pos' => [],
                'stages' => [
                    ['id' => 'stg-1', 'name' => 'Instalasi', 'ps' => '2026-01-01', 'pf' => '2026-01-10', 'as' => '2026-01-02', 'af' => '', 'prog' => 40, 'par' => false, 'status' => 'On Progress', 'note' => ''],
                    ['id' => 'stg-2', 'name' => 'Trial New Tooling', 'ps' => '2026-02-01', 'pf' => '2026-02-02', 'prog' => 100, 'status' => 'Completed',
                        'attachments' => [['id' => 'att-1', 'file' => 'PRJ-001_stage.pdf', 'name' => 'stage.pdf', 'size' => 5, 'type' => 'application/pdf', 'description' => 'Hasil trial', 'uploadedBy' => 'Zahra Pic']]],
                ],
                'quotations' => [
                    ['id' => 'quo-1', 'no' => 'Q-1', 'vendor' => 'PT Vendor Satu', 'amount' => 1000, 'status' => 'Terpilih', 'valid' => '2026-12-01', 'file' => 'PRJ-001_quote.pdf'],
                ],
                'milestones' => [],
            ]),
        ],
    );
    $src->table('projects')->insert(['id' => 'proj-b', 'name' => 'Improvement X', 'category' => 'Improvement', 'pic' => '', 'metadata' => json_encode(['stages' => [], 'quotations' => []])]);
    $src->table('project_stages')->insert([['id' => 's1'], ['id' => 's2']]);
    $src->table('project_quotations')->insert([['id' => 'q1']]);
    $src->table('project_files')->insert([
        ['id' => 'f1', 'project_id' => 'proj-a', 'file_name' => 'stage.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5, 'stored_name' => 'PRJ-001_stage.pdf'],
        ['id' => 'f2', 'project_id' => 'proj-a', 'file_name' => 'quote.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5, 'stored_name' => 'PRJ-001_quote.pdf'],
        ['id' => 'f3', 'project_id' => 'proj-a', 'file_name' => 'plain.png', 'mime_type' => 'image/png', 'size_bytes' => 9, 'stored_name' => 'PRJ-001_gone.png'],
        ['id' => 'f4', 'project_id' => null, 'file_name' => 'imp.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 3, 'stored_name' => 'IMP-imp-1_a.jpg'],
        ['id' => 'f5', 'project_id' => null, 'file_name' => 'tool.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 3, 'stored_name' => 'TLG-x_a.pdf'],
    ]);
    $src->table('project_logs')->insert([
        ['id' => 'l1', 'project_id' => 'proj-a', 'actor' => 'Zahra Pic', 'action' => 'Membuat project', 'created_at' => '2026-10-01 08:00:00'],
        ['id' => 'l2', 'project_id' => 'AUTH', 'actor' => 'Someone', 'action' => 'Login berhasil', 'created_at' => '2026-10-02 08:00:00'],
    ]);
    $src->table('settings')->insert([
        ['key_name' => 'tracker_state', 'value_json' => json_encode(['improvements' => [[
            'id' => 'imp-1', 'title' => 'Vacuum transfer', 'status' => 'Disetujui', 'decision' => 'To Project',
            'submittedBy' => 'Validasi', 'submittedAt' => 1794000000000, 'projectId' => 'proj-b',
            'attachments' => [['id' => 'ia-1', 'file' => 'IMP-imp-1_a.jpg', 'name' => 'imp.jpg', 'size' => 3, 'type' => 'image/jpeg']],
            'feedbackLog' => [['by' => 'Rozy']],
        ]]])],
        ['key_name' => 'tracker', 'value_json' => json_encode([
            'categories' => [['name' => 'Machine'], ['name' => 'Quality']],
            'vendors' => [['name' => 'PT Vendor Satu'], ['name' => 'PT Vendor Dua']],
            'templates' => [['id' => 'tpl_1', 'name' => 'New Machine', 'category' => 'Machine', 'stages' => [['name' => 'URS', 'days' => 14]]]],
        ])],
    ]);

    // Physical files: three of the five exist in a temp source dir.
    $dir = sys_get_temp_dir().'/pm_src_'.bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir.'/PRJ-001_stage.pdf', 'stage');   // 5 bytes
    file_put_contents($dir.'/PRJ-001_quote.pdf', 'quote');   // 5 bytes
    file_put_contents($dir.'/IMP-imp-1_a.jpg', 'img');       // 3 bytes

    return $dir;
}

function pmRunImport(string $dir, array $extra = []): int
{
    return Artisan::call('projects:import-legacy', ['--connection' => 'legacy_src', '--files-path' => $dir] + $extra);
}

beforeEach(function () {
    Storage::fake('project_files');
});

test('the pm_* migrations create every table', function () {
    foreach (['pm_projects', 'pm_stages', 'pm_milestones', 'pm_quotations', 'pm_files', 'pm_stage_templates', 'pm_improvements', 'pm_improvement_files'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
});

test('the migrations are no-ops when the tables already exist', function () {
    Project::create(['name' => 'Keep me']);

    foreach (glob(database_path('migrations/2026_11_17_00000*_create_pm_*.php')) as $file) {
        (require $file)->up();
    }

    expect(Project::where('name', 'Keep me')->count())->toBe(1);
});

test('dry run reads the source but writes nothing', function () {
    $dir = pmLegacySource();

    expect(pmRunImport($dir, ['--dry-run' => true]))->toBe(0);

    $out = Artisan::output();
    expect($out)->toContain('Dry run: nothing was written.');
    expect(Project::count())->toBe(0);
    expect(MasterOption::where('type', 'project_vendor')->count())->toBe(0);
    expect(ActivityLog::where('module', 'PROJECT')->count())->toBe(0);
});

test('import maps every entity, matches users and copies files with size verification', function () {
    $matched = User::factory()->create(['name' => 'Zahra Pic', 'email' => 'zahra.pic@example.test']);
    $dir = pmLegacySource();

    expect(pmRunImport($dir))->toBe(0);

    expect(Project::count())->toBe(2);
    $project = Project::where('legacy_id', 'proj-a')->firstOrFail();
    expect($project->pic_user_id)->toBe($matched->id)
        ->and($project->pic_name)->toBe('Zahra Pic')
        ->and($project->created_by_user_id)->toBeNull()          // unmatched => text only
        ->and($project->created_by_name)->toBe('Unknown Person')
        ->and($project->legacy_meta['log'])->toHaveCount(1);
    expect(User::where('name', 'Unknown Person')->exists())->toBeFalse(); // never creates accounts

    expect(ProjectStage::count())->toBe(2);
    $trial = ProjectStage::where('legacy_id', 'stg-2')->firstOrFail();
    expect($trial->stage_type)->toBe('Validation')
        ->and(ProjectStage::where('legacy_id', 'stg-1')->value('stage_type'))->toBe('General')
        ->and((string) ProjectStage::where('legacy_id', 'stg-1')->first()->actual_start->toDateString())->toBe('2026-01-02')
        ->and(ProjectStage::where('legacy_id', 'stg-1')->value('actual_end'))->toBeNull();

    $quotation = ProjectQuotation::where('legacy_id', 'quo-1')->firstOrFail();
    expect($quotation->vendor_option_id)->not->toBeNull()
        ->and(MasterOption::where('type', 'project_vendor')->count())->toBe(2)
        ->and(MasterOption::where('type', 'project_category')->count())->toBeGreaterThanOrEqual(2);

    // Files: 5 source rows => 4 pm_files + 1 improvement file.
    expect(ProjectFile::count())->toBe(4)->and(ImprovementFile::count())->toBe(1);
    expect(ProjectFile::where('legacy_id', 'f1')->first())
        ->context->toBe('stage')->description->toBe('Hasil trial')->stage_id->toBe($trial->id)->is_missing->toBeFalse();
    expect(ProjectFile::where('legacy_id', 'f2')->value('context'))->toBe('quotation')
        ->and($quotation->fresh()->file_id)->toBe(ProjectFile::where('legacy_id', 'f2')->value('id'))
        ->and(ProjectFile::where('legacy_id', 'f3')->value('is_missing'))->toBeTrue()
        ->and(ProjectFile::where('legacy_id', 'f5')->value('context'))->toBe('tooling');
    Storage::disk('project_files')->assertExists('PRJ-001_stage.pdf');
    Storage::disk('project_files')->assertMissing('PRJ-001_gone.png');
    expect(Storage::disk('project_files')->size('IMP-imp-1_a.jpg'))->toBe(3);

    $imp = Improvement::where('legacy_id', 'imp-1')->firstOrFail();
    expect($imp->status)->toBe('Disetujui')->and($imp->decision)->toBe('To Project')
        ->and($imp->submitted_by_name)->toBe('Validasi')->and($imp->submitted_by_user_id)->toBeNull()
        ->and($imp->project_id)->toBe(Project::where('legacy_id', 'proj-b')->value('id'))
        ->and($imp->feedback_log)->toHaveCount(1);

    expect(ActivityLog::where('module', 'PROJECT')->where('action', 'LEGACY_LOG')->count())->toBe(2)
        ->and((string) ActivityLog::where('record_id', 'legacy:l1')->value('created_at'))->toStartWith('2026-10-01 08:00:00');
});

test('running the import twice does not duplicate anything', function () {
    $dir = pmLegacySource();

    pmRunImport($dir);
    $first = [Project::count(), ProjectStage::count(), ProjectQuotation::count(), ProjectFile::count(), ImprovementFile::count(), Improvement::count(), MasterOption::count(), ActivityLog::count()];

    // Local edit must survive an unrelated field resync: trial link on a stage.
    ProjectStage::where('legacy_id', 'stg-2')->update(['trial_id' => 77]);

    pmRunImport($dir);
    $second = [Project::count(), ProjectStage::count(), ProjectQuotation::count(), ProjectFile::count(), ImprovementFile::count(), Improvement::count(), MasterOption::count(), ActivityLog::count()];

    // The second run adds only its own IMPORT summary log row.
    $first[7]++;
    expect($second)->toBe($first);
    expect(ProjectStage::where('legacy_id', 'stg-2')->value('trial_id'))->toBe(77);
});

test('an unreadable source connection fails cleanly', function () {
    config(['database.connections.legacy_src' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy_src');

    expect(pmRunImport(sys_get_temp_dir()))->toBe(1);
});

test('an explicit user map resolves names that do not match automatically', function () {
    $user = User::factory()->create(['name' => 'Someone Else', 'email' => 'mapped@example.test']);
    $dir = pmLegacySource();
    $mapFile = $dir.'/map.json';
    file_put_contents($mapFile, json_encode(['Zahra Pic' => 'mapped@example.test']));

    pmRunImport($dir, ['--user-map' => $mapFile]);

    expect(Project::where('legacy_id', 'proj-a')->value('pic_user_id'))->toBe($user->id);
});
