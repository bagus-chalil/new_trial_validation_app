<?php

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Tooling;
use App\Models\User;
use App\Services\Projects\ToolingWorkflow;
use App\Support\ToolingOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('project_files');
});

function toolingUser(string $department, string $role = 'Staff'): User
{
    return User::factory()->create(['role' => $role, 'department' => $department]);
}

function toolingRequestPayload(array $overrides = []): array
{
    return array_merge([
        'npd_no' => '1200/NPD/PKD/11/2026',
        'request_date' => '2026-11-20',
        'requested_by_name' => 'PRNI Tester',
        'client_code' => 'SMB',
        'product_name' => 'Micellar Refill',
        'product_type' => 'skin_care',
        'packaging_type' => 'Spout Pouch',
        'bom_primary' => 'Pouch, spout cap',
        'links' => [['title' => 'Drawing', 'url' => 'https://example.test/drawing.pdf']],
    ], $overrides);
}

function toolingStep(Tooling $tooling, string $step): string
{
    return route('projects.toolings.steps', ['tooling' => $tooling->id, 'step' => $step]);
}

test('tooling roles come from the department or review unit; admin and viewer hold none', function () {
    expect(ToolingWorkflow::rolesFor(toolingUser('PRNI')))->toBe(['prni'])
        ->and(ToolingWorkflow::rolesFor(toolingUser('RNI', 'RNI')))->toBe(['rni'])
        ->and(ToolingWorkflow::rolesFor(toolingUser('PRD')))->toBe(['production'])
        ->and(ToolingWorkflow::rolesFor(User::factory()->create(['role' => 'Staff', 'department' => 'QA', 'review_unit' => 'PROD'])))->toBe(['production'])
        ->and(ToolingWorkflow::rolesFor(toolingUser('PI')))->toBe(['pi'])
        ->and(ToolingWorkflow::rolesFor(toolingUser('PIE')))->toBe(['pi'])
        ->and(ToolingWorkflow::rolesFor(toolingUser('IT', 'Admin')))->toBe([])
        ->and(ToolingWorkflow::rolesFor(toolingUser('PRNI', 'Viewer')))->toBe([])
        ->and(ToolingWorkflow::rolesFor(toolingUser('QA')))->toBe([]);
});

test('a request cannot be saved while a required field is blank', function () {
    $prni = toolingUser('PRNI');

    foreach (['npd_no', 'request_date', 'requested_by_name', 'client_code', 'product_name', 'product_type', 'packaging_type', 'bom_primary'] as $field) {
        $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload([$field => '']))
            ->assertSessionHasErrors($field);
    }

    expect(Tooling::count())->toBe(0);
});

test('only PRNI (or admin) creates a request; everyone can read the list', function () {
    $this->actingAs(toolingUser('RNI'))->post(route('projects.toolings.store'), toolingRequestPayload())->assertForbidden();

    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload(['packaging_type' => 'Lip Tube']))
        ->assertRedirect();

    $tooling = Tooling::firstOrFail();
    expect($tooling->status)->toBe(ToolingOptions::STATUS_WAITING_RNI_SAMPLE)
        ->and($tooling->sample_status)->toBe('Menunggu Sampel')
        ->and($tooling->submitted_by_user_id)->toBe($prni->id)
        ->and($tooling->is_critical)->toBeTrue() // tube packaging
        ->and($tooling->links)->toBe([['title' => 'Drawing', 'url' => 'https://example.test/drawing.pdf']])
        ->and($tooling->history_log[0]['action'])->toBe('CREATE');

    $this->actingAs(toolingUser('QA', 'Viewer'))->get(route('projects.toolings.index', ['scope' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('toolings/index')->has('items', 1)->where('can.create', false));
});

test('the full flow reaches Approved with password e-signatures in the right order', function () {
    $prni = toolingUser('PRNI');
    $rni = toolingUser('RNI');
    $pie = toolingUser('PI');
    $prod = toolingUser('PRD');

    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();

    // PIE cannot check before the sample arrived and PRNI/RNI signed.
    $this->actingAs($pie)->post(toolingStep($tooling, 'pie-check'), ['tools' => [['tool' => 'Nozzle']], 'recommended_machine' => 'X', 'need_invest' => 'NO', 'risk_level' => 'Low risk', 'signature_password' => 'password'])
        ->assertForbidden();
    // Production cannot sign before PIE.
    $this->actingAs($prod)->post(toolingStep($tooling, 'sign-production'), ['signature_password' => 'password'])->assertForbidden();
    // RNI cannot sign the PRNI box.
    $this->actingAs($rni)->post(toolingStep($tooling, 'sign-prni'), ['signature_password' => 'password'])->assertForbidden();

    $this->actingAs($prni)->post(toolingStep($tooling, 'sign-prni'), ['signature_password' => 'password'])->assertRedirect();
    $this->actingAs($prni)->post(toolingStep($tooling, 'send-sample'), ['date' => '2026-11-21', 'note' => '5 pcs'])->assertRedirect();
    expect($tooling->fresh()->sample_status)->toBe('Sampel Sedang Dikirim');

    $this->actingAs($rni)->post(toolingStep($tooling, 'sign-rni'), [
        'formula_info' => 'Liquid, bubbles under pressure', 'volume_suggest' => '250 ml', 'signature_password' => 'password',
    ])->assertRedirect();
    expect($tooling->fresh()->status)->toBe(ToolingOptions::STATUS_WAITING_SAMPLE);

    Carbon::setTestNow('2026-11-20 09:00:00'); // Friday
    $this->actingAs($pie)->post(toolingStep($tooling, 'receive-sample'), ['date' => '2026-11-20'])->assertRedirect();
    Carbon::setTestNow();
    $fresh = $tooling->fresh();
    expect($fresh->status)->toBe(ToolingOptions::STATUS_PIE_CHECK)
        ->and($fresh->due_date->toDateString())->toBe('2026-11-27') // 5 working days
        ->and($fresh->sample_received_by_user_id)->toBe($pie->id);

    $this->actingAs($pie)->post(toolingStep($tooling, 'pie-check'), [
        'tools' => [['tool' => 'Nozzle', 'actual' => 'Ready', 'status' => 'OK'], ['tool' => 'Capping', 'actual' => 'Not ready', 'status' => 'Need New'], ['tool' => '', 'actual' => '']],
        'recommended_machine' => 'Tamru filling', 'need_invest' => 'YES', 'need_invest_note' => 'Capping tool', 'risk_level' => 'Medium risk',
        'signature_password' => 'password',
    ])->assertRedirect();
    $fresh = $tooling->fresh();
    expect($fresh->status)->toBe(ToolingOptions::STATUS_PRODUCTION)
        ->and($fresh->sample_status)->toBe('Selesai Dicek')
        ->and($fresh->items()->count())->toBe(2)
        ->and($fresh->pi_owner_user_id)->toBe($pie->id);

    $this->actingAs($prod)->post(toolingStep($tooling, 'sign-production'), ['note' => 'OK for line 3', 'signature_password' => 'password'])->assertRedirect();
    $fresh = $tooling->fresh();
    expect($fresh->status)->toBe(ToolingOptions::STATUS_APPROVED)
        ->and($fresh->approvals()->where('approved', true)->count())->toBe(4)
        ->and($fresh->approvalFor('production')->signer_user_id)->toBe($prod->id)
        ->and($fresh->approvalFor('production')->signature_method)->toBe('password')
        ->and(array_column($fresh->history_log, 'action'))->toBe(['CREATE', 'SIGN_PRNI', 'SEND_SAMPLE', 'SIGN_RNI', 'RECEIVE_SAMPLE', 'PIE_CHECK', 'SIGN_PRODUCTION'])
        ->and(ActivityLog::where('module', 'TOOLING')->where('record_id', (string) $tooling->id)->count())->toBe(7);

    // Finished: no more signing or editing.
    $this->actingAs($prni)->get(route('projects.toolings.edit', $tooling))->assertForbidden();
});

test('a wrong e-signature password is rejected and nothing is signed', function () {
    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();

    $this->actingAs($prni)->post(toolingStep($tooling, 'sign-prni'), ['signature_password' => 'wrong'])
        ->assertSessionHasErrors('signature_password');

    expect($tooling->fresh()->isSigned('prni'))->toBeFalse()
        ->and($tooling->fresh()->history_log)->toHaveCount(1);
});

test('hold stops signing until resumed; reject is final', function () {
    $prni = toolingUser('PRNI');
    $rni = toolingUser('RNI');
    $pie = toolingUser('PIE');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();
    $this->actingAs($prni)->post(toolingStep($tooling, 'sign-prni'), ['signature_password' => 'password']);

    $this->actingAs($rni)->post(toolingStep($tooling, 'hold'), ['reason' => 'x'])->assertForbidden();
    $this->actingAs($pie)->post(toolingStep($tooling, 'hold'), ['reason' => 'Drawing missing'])->assertRedirect();
    expect($tooling->fresh()->status)->toBe(ToolingOptions::STATUS_HOLD);

    $this->actingAs($rni)->post(toolingStep($tooling, 'sign-rni'), ['formula_info' => 'a', 'volume_suggest' => 'b', 'signature_password' => 'password'])->assertForbidden();
    // PRNI may still fix the request while on hold.
    $this->actingAs($prni)->put(route('projects.toolings.update', $tooling), toolingRequestPayload(['product_name' => 'Fixed']))->assertRedirect();

    $this->actingAs($pie)->post(toolingStep($tooling, 'resume'), [])->assertRedirect();
    expect($tooling->fresh()->status)->toBe(ToolingOptions::STATUS_WAITING_RNI_SAMPLE)
        ->and($tooling->fresh()->product_name)->toBe('Fixed');

    $this->actingAs($pie)->post(toolingStep($tooling, 'reject'), ['reason' => 'Not feasible'])->assertRedirect();
    expect($tooling->fresh()->status)->toBe(ToolingOptions::STATUS_REJECTED)
        ->and($tooling->fresh()->status_reason)->toBe('Not feasible');
    $this->actingAs($rni)->post(toolingStep($tooling, 'sign-rni'), ['formula_info' => 'a', 'volume_suggest' => 'b', 'signature_password' => 'password'])->assertForbidden();
});

test('Need Invest = YES can be promoted once to a Tooling Investment project', function () {
    $pie = toolingUser('PI');
    $tooling = Tooling::create(['npd_no' => '1300/NPD', 'product_name' => 'Cushion', 'packaging_type' => 'Cushion', 'status' => ToolingOptions::STATUS_PRODUCTION, 'need_invest' => 'YES', 'risk_level' => 'High risk', 'pi_owner_user_id' => $pie->id]);
    $tooling->approvals()->create(['role' => 'pi', 'approved' => true, 'signer_name' => 'PIE']);

    $this->actingAs(toolingUser('RNI'))->post(route('projects.toolings.promote', $tooling))->assertForbidden();
    $this->actingAs($pie)->post(route('projects.toolings.promote', $tooling))->assertRedirect();

    $project = Project::firstOrFail();
    expect($project->category)->toBe('Tooling NPD')
        ->and($project->priority)->toBe('High')
        ->and($project->pic_user_id)->toBe($pie->id)
        ->and($tooling->fresh()->project_id)->toBe($project->id);

    $this->actingAs($pie)->post(route('projects.toolings.promote', $tooling))->assertForbidden();
});

test('files upload and download through the policy; the detail page shows the next action', function () {
    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();

    $this->actingAs($prni)->post(route('projects.toolings.files.store', $tooling), [
        'file' => UploadedFile::fake()->createWithContent('drawing.pdf', "%PDF-1.4\n%test\n"),
        'description' => 'Technical drawing',
    ])->assertRedirect();
    $file = ProjectFile::where('tooling_id', $tooling->id)->firstOrFail();
    expect($file->context)->toBe('tooling');

    $this->actingAs(toolingUser('QA', 'Viewer'))->get(route('projects.toolings.files.show', [$tooling->id, $file->id]))->assertOk();
    $this->actingAs(toolingUser('QA', 'Viewer'))->post(route('projects.toolings.files.store', $tooling), ['file' => UploadedFile::fake()->create('x.pdf')])->assertForbidden();

    $this->actingAs($prni)->get(route('projects.toolings.show', $tooling))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('toolings/show')
            ->where('tooling.my_action', 'sign_prni')
            ->where('can.signPrni', true)
            ->where('can.pieCheck', false)
            ->has('files', 1)
            ->has('tooling.approvals', 4));
});

test('My Work lists the Check Tooling steps waiting on the user', function () {
    $prni = toolingUser('PRNI');
    $rni = toolingUser('RNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());

    $this->actingAs($rni)->get(route('my-work'))
        ->assertInertia(fn ($page) => $page
            ->where('myWork.myToolingsTotal', 1)
            ->where('myWork.myToolings.0.action', 'sign_rni'));

    $this->actingAs(toolingUser('QA'))->get(route('my-work'))
        ->assertInertia(fn ($page) => $page->where('myWork.myToolingsTotal', 0));

    $this->actingAs($rni)->get(route('projects.toolings.index', ['scope' => 'mine']))
        ->assertInertia(fn ($page) => $page->has('items', 1)->where('kpi.mine', 1));
});

test('the improvement reviewer records the after result and after evidence files', function () {
    $owner = toolingUser('PROD');
    $pi = toolingUser('PI', 'Team Leader');
    $improvement = Improvement::create(['title' => 'Guide', 'submitted_by_user_id' => $owner->id, 'status' => 'Disetujui']);

    $this->actingAs($pi)->post(route('projects.improvements.review', $improvement), [
        'status' => 'Selesai', 'decision' => 'Suggestion Only',
        'after_condition' => 'Guide centered', 'after_result' => 'Trial OK', 'after_date' => '2026-11-17', 'after_pic' => 'Rozy',
    ])->assertRedirect();
    $fresh = $improvement->fresh();
    expect($fresh->status)->toBe('Selesai')
        ->and($fresh->after_result)->toBe('Trial OK')
        ->and($fresh->after_date->toDateString())->toBe('2026-11-17');

    $this->actingAs($pi)->post(route('projects.improvements.files.store', $improvement), [
        'file' => UploadedFile::fake()->createWithContent('after.pdf', "%PDF-1.4\n%x\n"), 'phase' => 'after',
    ])->assertRedirect();
    expect(ImprovementFile::firstOrFail()->phase)->toBe('after');
});

test('drawings attached while creating a request are stored, and a bad one only warns', function () {
    $prni = toolingUser('PRNI');

    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload([
        'drawings' => [
            UploadedFile::fake()->createWithContent('drawing.pdf', "%PDF-1.4\n%test\n"),
            UploadedFile::fake()->createWithContent('run.php', '<?php echo 1;'),
        ],
    ]))->assertRedirect();

    $tooling = Tooling::firstOrFail(); // the request itself is always created
    $files = ProjectFile::where('tooling_id', $tooling->id)->get();

    expect($files)->toHaveCount(1)
        ->and($files->first()->file_name)->toBe('drawing.pdf');
});

test('an admin manages requests and may sign a box, but is not nagged in "your action"', function () {
    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();
    $admin = User::factory()->create(['role' => 'Admin', 'department' => 'PRNI']);

    expect($admin->can('signPrni', $tooling))->toBeTrue()
        ->and($admin->can('update', $tooling))->toBeTrue()
        ->and($admin->can('assign', $tooling))->toBeTrue()
        ->and($admin->can('delete', $tooling))->toBeTrue()
        ->and(ToolingWorkflow::pendingActionFor($tooling, $admin))->toBeNull();

    $this->actingAs($admin)->post(toolingStep($tooling, 'sign-prni'), ['signature_password' => 'password'])->assertRedirect();
    expect($tooling->fresh()->isSigned('prni'))->toBeTrue();
});

test('a box assigned to one person is signed only by that person or an admin', function () {
    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();
    $rniA = toolingUser('RNI');
    $rniB = toolingUser('RNI');

    $this->actingAs($prni)->post(toolingStep($tooling, 'assign'), ['assignees' => ['rni' => $rniA->id]])->assertRedirect();
    expect($tooling->fresh()->assigneeFor('rni'))->toBe($rniA->id);

    $payload = ['formula_info' => 'F', 'volume_suggest' => '100', 'signature_password' => 'password'];
    $this->actingAs($rniB)->post(toolingStep($tooling, 'sign-rni'), $payload)->assertForbidden();
    $this->actingAs($rniA)->post(toolingStep($tooling, 'sign-rni'), $payload)->assertRedirect();
    expect($tooling->fresh()->isSigned('rni'))->toBeTrue();
});

test('with nobody assigned anyone in the department group may sign', function () {
    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();
    $rni = toolingUser('RNI');

    expect($rni->can('signRni', $tooling))->toBeTrue();

    $this->actingAs($prni)->post(toolingStep($tooling, 'assign'), ['assignees' => ['rni' => $rni->id]]);
    $this->actingAs($prni)->post(toolingStep($tooling, 'assign'), ['assignees' => ['rni' => '']]);
    expect($tooling->fresh()->assigneeFor('rni'))->toBeNull()
        ->and(toolingUser('RNI')->can('signRni', $tooling->fresh()))->toBeTrue();
});

test('assignment is limited to PRNI or admin and to users of that box department', function () {
    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();
    $rni = toolingUser('RNI');
    $pie = toolingUser('PIE');

    $this->actingAs($rni)->post(toolingStep($tooling, 'assign'), ['assignees' => ['rni' => $rni->id]])->assertForbidden();

    // a PIE user cannot be put on the RNI box
    $this->actingAs($prni)->post(toolingStep($tooling, 'assign'), ['assignees' => ['rni' => $pie->id]])->assertSessionHasErrors('assignees');
    expect($tooling->fresh()->assigneeFor('rni'))->toBeNull();
});

test('a signed box keeps its signer when assignments change', function () {
    $prni = toolingUser('PRNI');
    $this->actingAs($prni)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();
    $otherPrni = toolingUser('PRNI');

    $this->actingAs($prni)->post(toolingStep($tooling, 'sign-prni'), ['signature_password' => 'password'])->assertRedirect();
    $this->actingAs($prni)->post(toolingStep($tooling, 'assign'), ['assignees' => ['prni' => $otherPrni->id]])->assertRedirect();

    expect($tooling->fresh()->assigneeFor('prni'))->toBeNull();
});

test('one person cannot sign two boxes on the same request', function () {
    $both = User::factory()->create(['role' => 'Staff', 'department' => 'PRNI', 'review_unit' => 'RNI']);
    $this->actingAs($both)->post(route('projects.toolings.store'), toolingRequestPayload());
    $tooling = Tooling::firstOrFail();

    $this->actingAs($both)->post(toolingStep($tooling, 'sign-prni'), ['signature_password' => 'password'])->assertRedirect();
    expect($tooling->fresh()->isSigned('prni'))->toBeTrue();

    $this->actingAs($both)->post(toolingStep($tooling, 'sign-rni'), ['signature_password' => 'password', 'formula_info' => 'x'])->assertForbidden();
    expect($tooling->fresh()->isSigned('rni'))->toBeFalse();

    // A different RNI person can still sign.
    $rni = toolingUser('RNI');
    expect($rni->can('signRni', $tooling))->toBeTrue();
});
