<?php

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\MasterOption;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectQuotation;
use App\Models\ProjectStage;
use App\Models\Tooling;
use App\Models\ToolingApproval;
use App\Models\ToolingItem;
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

test('the Check Tooling migration is additive and a re-run is a no-op', function () {
    foreach (['pm_toolings', 'pm_tooling_items', 'pm_tooling_approvals'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
    expect(Schema::hasColumn('pm_files', 'tooling_id'))->toBeTrue()
        ->and(Schema::hasColumn('pm_improvements', 'after_result'))->toBeTrue()
        ->and(Schema::hasColumn('pm_improvement_files', 'phase'))->toBeTrue();

    Tooling::create(['npd_no' => 'keep']);
    (require database_path('migrations/2026_11_23_000001_create_pm_tooling_tables.php'))->up();

    expect(Tooling::where('npd_no', 'keep')->count())->toBe(1);
});

test('import brings Check Tooling requests with tools, legacy signatures, linked files and improvement after-results', function () {
    $signer = User::factory()->create(['name' => 'Andra', 'email' => 'andra@example.test']);
    $dir = pmLegacySource();
    $sig = 'data:image/png;base64,'.base64_encode('png-bytes');

    $src = DB::connection('legacy_src');
    $state = json_decode((string) $src->table('settings')->where('key_name', 'tracker_state')->value('value_json'), true);
    $state['improvements'][0]['afterCondition'] = 'Guide centered';
    $state['improvements'][0]['afterResult'] = 'Trial OK';
    $state['improvements'][0]['afterDate'] = '2026-11-17';
    $state['improvements'][0]['afterPic'] = 'Rozy';
    $state['improvements'][0]['afterAttachments'] = [['id' => 'aa-1', 'file' => 'IMP-AFTER-imp-1_b.jpg', 'name' => 'after.jpg', 'size' => 2, 'type' => 'image/jpeg', 'description' => 'After photo']];
    $state['toolings'] = [
        [
            'id' => 'tlg-1', 'npdNo' => '1138/NPD/PKD/11/2026', 'productName' => 'Cushion SKaeLab', 'packagingType' => 'Cushion',
            'status' => 'Review Production', 'sampleStatus' => 'Selesai Dicek', 'submittedBy' => 'PRNI', 'submittedAt' => 1794000000000,
            'piOwner' => 'Unknown PIE', 'needInvest' => 'NO', 'riskLevel' => 'Low risk', 'productType' => 'make_up',
            'tools' => [['id' => 't1', 'tool' => 'Turn Table Set', 'actual' => 'OK', 'status' => 'OK'], ['id' => 't2', 'tool' => '', 'actual' => '', 'status' => '']],
            'approvals' => [
                ['role' => 'prni', 'approved' => true, 'name' => 'Andra', 'date' => '2026-11-12', 'at' => 1794476325928, 'sig' => $sig, 'signedBy' => 'PRNI'],
                ['role' => 'rni', 'approved' => true, 'name' => 'Naya - RNI', 'date' => '2026-11-23', 'sig' => 'javascript:alert(1)', 'signedBy' => 'RNI'],
                ['role' => 'pi', 'approved' => true, 'name' => 'Zahra', 'date' => '2026-11-23'],
            ],
            'attachments' => [['id' => 'ta-1', 'file' => 'TLG-tlg-1_a.pdf', 'name' => 'drawing.pdf', 'size' => 3, 'type' => 'application/pdf', 'description' => 'Drawing']],
            'links' => [['id' => 'l1', 'title' => 'Spec', 'url' => 'https://example.test/spec'], ['title' => 'Bad', 'url' => 'javascript:x']],
            'historyLog' => [['by' => 'PRNI', 'at' => 1794476325928, 'status' => 'Submitted', 'action' => 'Membuat request Check Tooling baru', 'note' => '']],
        ],
        ['id' => 'tlg-2', 'npdNo' => '1150/NPD', 'status' => 'Submitted', 'sampleStatus' => 'Menunggu Sampel', 'approvals' => [['role' => 'prni', 'approved' => true, 'name' => 'Mella']]],
    ];
    $src->table('settings')->where('key_name', 'tracker_state')->update(['value_json' => json_encode($state)]);
    // The fixture's TLG-x_a.pdf (f5) stays an orphan; add the linked one and an after file.
    $src->table('project_files')->insert([
        ['id' => 'f6', 'project_id' => null, 'file_name' => 'drawing.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 3, 'stored_name' => 'TLG-tlg-1_a.pdf'],
        ['id' => 'f7', 'project_id' => null, 'file_name' => 'after.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 2, 'stored_name' => 'IMP-AFTER-imp-1_b.jpg'],
    ]);
    file_put_contents($dir.'/TLG-tlg-1_a.pdf', 'pdf');
    file_put_contents($dir.'/IMP-AFTER-imp-1_b.jpg', 'ab');

    // Simulate an earlier (pre-Fase-4b) import that put the after file in pm_files.
    ProjectFile::create(['legacy_id' => 'f7', 'context' => 'project', 'file_name' => 'after.jpg', 'mime_type' => 'image/jpeg', 'stored_name' => 'IMP-AFTER-imp-1_b.jpg']);

    expect(pmRunImport($dir, ['--dry-run' => true]))->toBe(0);
    expect(Artisan::output())->toContain('check toolings')
        ->and(Tooling::count())->toBe(0);

    expect(pmRunImport($dir))->toBe(0);

    $t = Tooling::where('legacy_id', 'tlg-1')->firstOrFail();
    expect($t->status)->toBe('Review Production')
        ->and($t->product_type)->toBe('make_up')
        ->and($t->pi_owner_user_id)->toBeNull()
        ->and($t->pi_owner_name)->toBe('Unknown PIE')
        ->and($t->items()->count())->toBe(2)
        ->and($t->links)->toBe([['title' => 'Spec', 'url' => 'https://example.test/spec']])
        ->and($t->history_log)->toHaveCount(1);
    $prni = $t->approvalFor('prni');
    expect($prni->signer_user_id)->toBe($signer->id)
        ->and($prni->signature_method)->toBe('legacy')
        ->and($prni->signature_image)->toBe($sig)
        ->and($prni->signed_by_account)->toBe('PRNI')
        ->and($t->approvalFor('rni')->signature_image)->toBeNull(); // not a data: image => dropped

    // 'Submitted' is not a real status: derived from signatures + sample instead.
    expect(Tooling::where('legacy_id', 'tlg-2')->value('status'))->toBe('Menunggu RNI & Sampel');

    expect(ProjectFile::where('legacy_id', 'f6')->value('tooling_id'))->toBe($t->id)
        ->and(ProjectFile::where('legacy_id', 'f6')->value('description'))->toBe('Drawing')
        ->and(ProjectFile::where('legacy_id', 'f5')->value('tooling_id'))->toBeNull()
        ->and(ProjectFile::where('legacy_id', 'f7')->exists())->toBeFalse();

    $after = ImprovementFile::where('legacy_id', 'f7')->firstOrFail();
    $imp = Improvement::where('legacy_id', 'imp-1')->firstOrFail();
    expect($after->phase)->toBe('after')
        ->and($after->improvement_id)->toBe($imp->id)
        ->and($after->description)->toBe('After photo')
        ->and($imp->after_result)->toBe('Trial OK')
        ->and($imp->after_date->toDateString())->toBe('2026-11-17');

    // Idempotent.
    $counts = fn () => [Tooling::count(), ToolingItem::count(), ToolingApproval::count(), ProjectFile::count(), ImprovementFile::count()];
    $before = $counts();
    pmRunImport($dir);
    expect($counts())->toBe($before);
});
