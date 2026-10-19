<?php

use App\Models\LineConfigurationLane;
use App\Models\MasterOption;
use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// A fresh test DB leaves the PIE lane open to everyone, so pin it to the PIE team like production.
beforeEach(function () {
    $pie = MasterOption::firstWhere(['type' => 'reviewer_department', 'name' => 'PIE']);
    LineConfigurationLane::where('stage_key', 'approved_pie')->update(['required_team_id' => $pie->id]);
});

function makeMonitorTrial(array $attributes = [], ?int $idleDays = null): Trial
{
    $trial = Trial::create(array_merge([
        'trial_code' => 'TRIAL-'.uniqid(),
        'product_name' => 'Sample Product',
        'finish_good_code' => 'FG-001',
        'product_type' => 'Tube',
        'progress_status' => 'In Review',
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));

    if ($idleDays !== null) {
        DB::table('trials_header')->where('id', $trial->id)->update([
            'updated_at' => Carbon::now()->subDays($idleDays),
        ]);
    }

    return $trial;
}

test('guests are redirected to the login page', function () {
    $this->get('/monitoring')->assertRedirect(route('login'));
});

test('any logged-in user sees every active trial regardless of who owns it', function () {
    $stranger = User::factory()->create(['role' => 'Viewer', 'department' => 'Other']);
    makeMonitorTrial(['trial_code' => 'TRIAL-REVIEW', 'progress_status' => 'In Review', 'pending_with' => 'QAC']);
    makeMonitorTrial(['trial_code' => 'TRIAL-APPROVAL', 'progress_status' => 'Ready for Approval', 'pending_with' => 'manager qac']);
    makeMonitorTrial(['trial_code' => 'TRIAL-REVISION', 'progress_status' => 'Need Revision', 'pending_with' => 'Staff']);

    $this->actingAs($stranger)->get('/monitoring')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitoring/index')
            ->where('trials.total', 3));
});

test('another users draft is listed but its detail link stays closed', function () {
    $stranger = User::factory()->create(['role' => 'Viewer', 'department' => 'Other']);
    makeMonitorTrial(['trial_code' => 'TRIAL-DRAFT', 'progress_status' => 'Draft']);
    makeMonitorTrial(['trial_code' => 'TRIAL-OPEN', 'progress_status' => 'In Review', 'pending_with' => 'QAC']);

    $this->actingAs($stranger)->get('/monitoring')
        ->assertInertia(fn ($page) => $page
            ->where('trials.total', 2)
            ->where('trials.data', fn ($rows) => collect($rows)->firstWhere('trial_code', 'TRIAL-DRAFT')['can_view'] === false
                && collect($rows)->firstWhere('trial_code', 'TRIAL-OPEN')['can_view'] === true));
});

test('finished, deleted and archived trials are excluded', function () {
    $user = User::factory()->create(['role' => 'Viewer']);
    makeMonitorTrial(['progress_status' => 'Approved']);
    makeMonitorTrial(['progress_status' => 'Rejected']);
    $deleted = makeMonitorTrial(['progress_status' => 'In Review']);
    $archived = makeMonitorTrial(['progress_status' => 'In Review']);
    makeMonitorTrial(['trial_code' => 'TRIAL-ACTIVE', 'progress_status' => 'In Review']);
    DB::table('trials_header')->where('id', $deleted->id)->update(['deleted_at' => Carbon::now()]);
    DB::table('trials_header')->where('id', $archived->id)->update(['archived_at' => Carbon::now()]);

    $this->actingAs($user)->get('/monitoring')
        ->assertInertia(fn ($page) => $page->where('trials.total', 1));
});

test('the oldest idle trial is listed first and idle days are computed', function () {
    $user = User::factory()->create(['role' => 'Viewer']);
    makeMonitorTrial(['trial_code' => 'TRIAL-NEW'], 2);
    makeMonitorTrial(['trial_code' => 'TRIAL-OLD'], 20);

    $this->actingAs($user)->get('/monitoring')
        ->assertInertia(fn ($page) => $page
            ->where('trials.data.0.trial_code', 'TRIAL-OLD')
            ->where('trials.data.0.idle_days', 20)
            ->where('trials.data.1.idle_days', 2));
});

test('in review trials count once per pending department and the holder filter narrows the list', function () {
    $user = User::factory()->create(['role' => 'Viewer']);
    makeMonitorTrial(['trial_code' => 'TRIAL-BOTH', 'pending_with' => 'PROD,QAC'], 10);
    makeMonitorTrial(['trial_code' => 'TRIAL-QAC', 'pending_with' => 'QAC'], 30);
    makeMonitorTrial(['trial_code' => 'TRIAL-APPROVAL', 'progress_status' => 'Ready for Approval', 'pending_with' => 'manager qac']);

    $this->actingAs($user)->get('/monitoring')
        ->assertInertia(fn ($page) => $page
            ->where('holders', fn ($holders) => collect($holders)->firstWhere('name', 'QAC')['count'] === 2
                && collect($holders)->firstWhere('name', 'QAC')['oldest_days'] === 30
                && collect($holders)->firstWhere('name', 'PROD')['count'] === 1
                && collect($holders)->firstWhere('name', 'manager qac')['kind'] === 'approver'));

    $this->actingAs($user)->get('/monitoring?holder=PROD')
        ->assertInertia(fn ($page) => $page
            ->where('trials.total', 1)
            ->where('trials.data.0.trial_code', 'TRIAL-BOTH'));
});

test('stage cards summarise count, average and oldest idle days', function () {
    $user = User::factory()->create(['role' => 'Viewer']);
    makeMonitorTrial(['pending_with' => 'QAC'], 10);
    makeMonitorTrial(['pending_with' => 'QAC'], 30);

    $this->actingAs($user)->get('/monitoring')
        ->assertInertia(fn ($page) => $page
            ->where('stages', fn ($stages) => collect($stages)->firstWhere('status', 'In Review')['count'] === 2
                && (float) collect($stages)->firstWhere('status', 'In Review')['avg_days'] === 20.0
                && collect($stages)->firstWhere('status', 'In Review')['oldest_days'] === 30
                && collect($stages)->firstWhere('status', 'In Review')['stale'] === 1));
});

test('the status and search filters narrow the list', function () {
    $user = User::factory()->create(['role' => 'Viewer']);
    makeMonitorTrial(['trial_code' => 'TRIAL-ONE', 'product_name' => 'Alpha Serum', 'progress_status' => 'In Review']);
    makeMonitorTrial(['trial_code' => 'TRIAL-TWO', 'product_name' => 'Beta Cream', 'progress_status' => 'Need Revision']);

    $this->actingAs($user)->get('/monitoring?status=Need Revision')
        ->assertInertia(fn ($page) => $page->where('trials.total', 1)->where('trials.data.0.trial_code', 'TRIAL-TWO'));

    $this->actingAs($user)->get('/monitoring?q=Alpha')
        ->assertInertia(fn ($page) => $page->where('trials.total', 1)->where('trials.data.0.trial_code', 'TRIAL-ONE'));
});

test('the report opened from the all trials page is view only for someone who could otherwise act', function () {
    $owner = User::factory()->create(['role' => 'Staff', 'email' => 'owner@local.test']);
    $trial = makeMonitorTrial(['progress_status' => 'Need Revision', 'pending_with' => 'Staff']);

    $this->actingAs($owner)->get("/trials/{$trial->id}/report")
        ->assertInertia(fn ($page) => $page
            ->where('readOnlyMode', false)
            ->where('canEdit', true));

    $this->actingAs($owner)->get("/trials/{$trial->id}/report?from=overview")
        ->assertInertia(fn ($page) => $page
            ->where('readOnlyMode', true)
            ->where('canEdit', false)
            ->where('canUploadAdditionalAttachment', false)
            ->where('canApprove', false)
            ->where('pendingReviews', [])
            ->where('editableReviews', [])
            ->where('canEditLineConfigurationReport', false)
            ->where('actionMenuKey', 'common.nav.need_revision'));
});

test('a viewer with nothing to act on gets no redirect hint in view only mode', function () {
    $viewer = User::factory()->create(['role' => 'Viewer', 'department' => 'Other']);
    $trial = makeMonitorTrial(['progress_status' => 'In Review', 'pending_with' => 'QAC']);

    $this->actingAs($viewer)->get("/trials/{$trial->id}/report?from=overview")
        ->assertInertia(fn ($page) => $page
            ->where('readOnlyMode', true)
            ->where('actionMenuKey', null));
});
