<?php

use App\Actions\Trials\MarkTrialLineConfigurationReportSignOff;
use App\Mail\TrialLineConfigurationReturnedMail;
use App\Mail\TrialLineConfigurationSignOffRequestedMail;
use App\Models\ActivityLog;
use App\Models\LineConfigurationLane;
use App\Models\MasterOption;
use App\Models\Notification;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// PIE makes the report (the 'approved_pie' lane gates editing); PROD is the
// checker. A fresh test DB seeds the PIE lane with no required team, so pin it.
beforeEach(function () {
    $pie = MasterOption::firstWhere(['type' => 'reviewer_department', 'name' => 'PIE']);
    LineConfigurationLane::where('stage_key', 'approved_pie')->update(['required_team_id' => $pie->id]);
});

function makeLcrTrial(array $attributes = []): Trial
{
    return Trial::create([
        'trial_code' => $attributes['trial_code'] ?? 'TRIAL-LCR-1',
        'product_name' => 'Sample Product',
        'product_type' => 'Tube',
        'progress_status' => $attributes['progress_status'] ?? 'Approved',
        'final_decision' => $attributes['final_decision'] ?? 'Approved',
        'current_step' => 'Closed',
        'created_by' => 'owner@local.test',
        'revision_no' => 0,
    ]);
}

/**
 * A fully-filled save payload — every field on the Line Configuration
 * Report form is required (incl. both approvers), so each test only
 * overrides what it actually cares about.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lcrPayload(array $overrides = []): array
{
    return [
        'report_date' => '2026-09-15',
        'client_name' => 'JUV',
        'pic' => 'Fauzi',
        'operator' => 'Septiawan',
        'validation_name' => 'Trial Sealing',
        'total_qty' => '100',
        'setting_qty' => '10',
        'pass_qty' => '90',
        'ng_qty' => '0 Pcs (0%)',
        'opinion' => 'Low risk',
        'production_standard' => [
            ['line' => 'F', 'workers' => '1', 'capacity' => '70', 'remark' => 'Standard'],
        ],
        'line_configuration' => [
            ['equipment' => 'SC Tube 10', 'process' => 'Supply Tube', 'worker' => '1', 'trial_status' => 'Pass', 'remark' => '-'],
        ],
        ...$overrides,
    ];
}

test('a PIE reviewer can view and edit the line configuration report on the report page', function () {
    $reviewer = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial();

    $response = $this->actingAs($reviewer)->get(route('trials.report.show', $trial->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('canEditLineConfigurationReport', true)
        ->where('lineConfigurationReport', null));
});

test('a reviewer from another department cannot edit the line configuration report', function () {
    $reviewer = User::factory()->reviewUnit('QAC')->create();
    $trial = makeLcrTrial();

    $response = $this->actingAs($reviewer)->get(route('trials.report.show', $trial->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('canEditLineConfigurationReport', false));

    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), lcrPayload(['client_name' => 'Nope']))
        ->assertForbidden();
});

test('a PIE reviewer can save a line configuration report, and blank rows are dropped', function () {
    $reviewer = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial();

    $response = $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), lcrPayload([
        'report_date' => '2026-09-15',
        'client_name' => 'JUV',
        'pic' => 'Fauzi',
        'operator' => 'Septiawan',
        'validation_name' => 'Trial Sealing',
        'total_qty' => 'General Pcs',
        'setting_qty' => 'General Pcs',
        'pass_qty' => 'General Pcs',
        'ng_qty' => '0 Pcs (0%)',
        'capacity_label' => 'PRD Speed (pcs/min)',
        'production_standard' => [
            ['line' => 'F', 'workers' => '1', 'capacity' => '70', 'remark' => 'Base on standar productivity PROD'],
            ['line' => '', 'workers' => '', 'capacity' => '', 'remark' => ''],
        ],
        'line_configuration' => [
            ['no' => '1', 'equipment' => 'SC Tube 10', 'process' => 'Supply Tube', 'worker' => '1', 'trial_status' => 'No Trial', 'remark' => '-'],
            ['no' => '', 'equipment' => '', 'process' => '', 'worker' => '', 'trial_status' => 'No Trial', 'remark' => ''],
        ],
        'opinion' => 'Other process is low risk so trial only shrink part',
    ]));

    $response->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->client_name)->toBe('JUV');
    expect($report->production_standard)->toHaveCount(1);
    expect($report->line_configuration)->toHaveCount(1);
    expect($report->updated_by_user_id)->toBe($reviewer->id);
    expect($report->version)->toBe(1);
    expect($report->is_locked)->toBeFalse();

    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'CREATE')->exists())->toBeTrue();
});

test('the line configuration report stays editable even after the trial has been decided, unlike department reviews', function () {
    $reviewer = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial(['progress_status' => 'Approved', 'final_decision' => 'Approved']);

    TrialLineConfigurationReport::create(['trial_id' => $trial->id, 'client_name' => 'Old Client']);

    $response = $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), lcrPayload([
        'client_name' => 'Updated Client',
    ]));

    $response->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->client_name)->toBe('Updated Client');
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'UPDATE')->exists())->toBeTrue();
});

test('a different PIE reviewer cannot edit a report someone else already drafted, only the drafter (or admin) can', function () {
    $drafter = User::factory()->reviewUnit('PIE')->create();
    $otherProdReviewer = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial();

    // An unsubmitted draft (no approvers yet — e.g. one saved before every
    // field became required): a normal save now always submits it.
    TrialLineConfigurationReport::create([
        'trial_id' => $trial->id,
        'client_name' => 'Drafted by first reviewer',
        'updated_by_user_id' => $drafter->id,
    ]);

    $page = $this->actingAs($otherProdReviewer)->get(route('trials.report.show', $trial->id));
    $page->assertInertia(fn ($p) => $p->where('canEditLineConfigurationReport', false));

    $this->actingAs($otherProdReviewer)
        ->put(route('trials.line-configuration.update', $trial->id), lcrPayload(['client_name' => 'Sneaky edit by another reviewer']))
        ->assertForbidden();

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail()->client_name)->toBe('Drafted by first reviewer');

    // The original drafter can still edit their own report freely.
    $page = $this->actingAs($drafter)->get(route('trials.report.show', $trial->id));
    $page->assertInertia(fn ($p) => $p->where('canEditLineConfigurationReport', true));
});

test('an admin can also edit the line configuration report regardless of review team', function () {
    $admin = User::factory()->role('Admin')->create();
    $trial = makeLcrTrial();

    $response = $this->actingAs($admin)->put(route('trials.line-configuration.update', $trial->id), lcrPayload([
        'client_name' => 'Admin Edit',
    ]));

    $response->assertRedirect(route('trials.report.show', $trial->id));
    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail()->client_name)->toBe('Admin Edit');
});

test('approved_pie/checked_prod can no longer be set directly through the general save form', function () {
    $reviewer = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), lcrPayload([
            'approved_pie' => '1',
            'approved_pie_by' => 'Should Be Ignored',
            'checked_prod' => '1',
        ]))
        ->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->approved_pie)->toBeFalse();
    expect($report->approved_pie_by)->toBeNull();
    expect($report->checked_prod)->toBeFalse();
});

/** Submits a complete report as $maker; returns the report. */
function submitLcr(User $maker, Trial $trial, array $overrides = []): TrialLineConfigurationReport
{
    test()->actingAs($maker)->put(route('trials.line-configuration.update', $trial->id), lcrPayload(['intent' => 'submit', ...$overrides]));

    return TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->firstOrFail();
}

/** A PIE Leader: explicitly listed as a signer of the approved_pie lane. */
function makePieLeader(array $attrs = []): User
{
    $leader = User::factory()->create(['role' => 'Staff', 'department' => 'PIE', ...$attrs]);
    $lane = LineConfigurationLane::where('stage_key', 'approved_pie')->firstOrFail();
    DB::table('line_configuration_lane_users')->insert(['lane_id' => $lane->id, 'user_id' => $leader->id, 'created_at' => now(), 'updated_at' => now()]);

    return $leader;
}

test('nobody is picked on the form: submit puts the report in the chain and notifies PROD signers only', function () {
    Mail::fake();
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $leader = makePieLeader();
    $trial = makeLcrTrial();

    $report = submitLcr($maker, $trial);

    expect($report->isSubmittedForApproval())->toBeTrue();
    expect($report->currentApprovalStage())->toBe('checked_prod');
    expect(Notification::where('user_id', $prod->id)->where('type', 'approval')->count())->toBe(1);
    expect(Notification::where('user_id', $leader->id)->count())->toBe(0);
    Mail::assertSent(TrialLineConfigurationSignOffRequestedMail::class, fn ($m) => $m->hasTo($prod->email) && $m->fieldLabel === 'Acknowledged (PROD)');
    Mail::assertNotSent(TrialLineConfigurationSignOffRequestedMail::class, fn ($m) => $m->hasTo($leader->email));
});

test('PROD acknowledges first, then a PIE Leader approves, each stamping own name and time', function () {
    Mail::fake();
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create(['name' => 'Siti PROD']);
    $leader = makePieLeader(['name' => 'Mr DJ']);
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);

    $this->actingAs($leader)->post(route('trials.line-configuration.approve-pie', $trial->id))->assertForbidden();

    $this->actingAs($prod)->post(route('trials.line-configuration.check-prod', $trial->id), ['comment' => 'Line sesuai'])
        ->assertRedirect(route('trials.report.show', $trial->id));
    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->checked_prod)->toBeTrue()->and($report->checked_prod_by)->toBe('Siti PROD')->and($report->checked_prod_comment)->toBe('Line sesuai');

    expect(Notification::where('user_id', $leader->id)->where('type', 'approval')->count())->toBe(1);
    Mail::assertSent(TrialLineConfigurationSignOffRequestedMail::class, fn ($m) => $m->hasTo($leader->email) && $m->fieldLabel === 'Approved (PIE Leader)');

    $this->actingAs($leader)->post(route('trials.line-configuration.approve-pie', $trial->id))
        ->assertRedirect(route('trials.report.show', $trial->id));
    $report->refresh();
    expect($report->approved_pie)->toBeTrue()->and($report->approved_pie_by)->toBe('Mr DJ')->and($report->approved_pie_at)->not->toBeNull();
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'CHECK_PROD')->exists())->toBeTrue();
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'APPROVE_PIE')->exists())->toBeTrue();
});

test('only eligible users can sign: not other teams, not a plain PIE maker, not even Admin', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $qac = User::factory()->reviewUnit('QAC')->create();
    $admin = User::factory()->role('Admin')->create();
    $leader = makePieLeader();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);

    foreach ([$qac, $admin, $maker, $leader] as $nope) {
        $this->actingAs($nope)->post(route('trials.line-configuration.check-prod', $trial->id))->assertForbidden();
    }

    $this->actingAs($prod)->post(route('trials.line-configuration.check-prod', $trial->id));

    foreach ([$maker, $admin, $qac, $prod] as $nope) {
        $this->actingAs($nope)->post(route('trials.line-configuration.approve-pie', $trial->id))->assertForbidden();
    }
    $this->actingAs($leader)->post(route('trials.line-configuration.approve-pie', $trial->id))->assertRedirect();
});

test('a draft or never-submitted report cannot be signed by anyone', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();
    $this->actingAs($maker)->put(route('trials.line-configuration.update', $trial->id), ['intent' => 'draft', 'client_name' => 'x']);

    $this->actingAs($prod)->post(route('trials.line-configuration.check-prod', $trial->id))->assertForbidden();
    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail()->isSubmittedForApproval())->toBeFalse();
});

test('once submitted the report is locked for the maker until an admin overrides, and re-saving does not re-notify', function () {
    Mail::fake();
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $admin = User::factory()->role('Admin')->create();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);

    $this->actingAs($maker)->put(route('trials.line-configuration.update', $trial->id), lcrPayload(['client_name' => 'Sneaky']))->assertForbidden();
    $this->actingAs($admin)->put(route('trials.line-configuration.update', $trial->id), lcrPayload(['client_name' => 'Admin override']))->assertRedirect();

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail()->client_name)->toBe('Admin override');
    expect(Notification::where('user_id', $prod->id)->count())->toBe(1);
    Mail::assertSent(TrialLineConfigurationSignOffRequestedMail::class, 1);
});

test('the PROD signer can view the trial report with no other access', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $viewer = User::factory()->create(['role' => 'Viewer', 'department' => 'PROD', 'review_unit' => 'PROD']);
    $trial = makeLcrTrial(['progress_status' => 'In Review', 'final_decision' => null]);
    submitLcr($maker, $trial);

    $this->actingAs($viewer)->get(route('trials.report.show', $trial->id))->assertOk();
});

test('returning needs 5 words, is allowed to whoever holds the active stage, and restarts from PROD', function () {
    Mail::fake();
    $maker = User::factory()->reviewUnit('PIE')->create(['name' => 'Dedi Drafter']);
    $prod = User::factory()->reviewUnit('PROD')->create(['name' => 'Siti PROD']);
    $leader = makePieLeader();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);
    $reason = 'Data produksi belum sesuai standar mohon direvisi ulang sebelum disetujui kembali';

    $this->actingAs($prod)->post(route('trials.line-configuration.return', $trial->id), ['reason' => 'Terlalu singkat'])->assertInvalid(['reason']);
    $this->actingAs($leader)->post(route('trials.line-configuration.return', $trial->id), ['reason' => $reason])->assertForbidden();

    $this->actingAs($prod)->post(route('trials.line-configuration.check-prod', $trial->id));
    $this->actingAs($leader)->post(route('trials.line-configuration.return', $trial->id), ['reason' => $reason])->assertRedirect();

    $all = TrialLineConfigurationReport::where('trial_id', $trial->id)->orderBy('version')->get();
    expect($all)->toHaveCount(2);
    [$v1, $v2] = [$all[0], $all[1]];
    expect($v1->is_locked)->toBeTrue()->and($v1->return_reason)->toBe($reason)->and($v1->checked_prod)->toBeTrue();
    expect($v2->is_locked)->toBeFalse()->and($v2->checked_prod)->toBeFalse()->and($v2->approved_pie)->toBeFalse()
        ->and($v2->submitted_at)->toBeNull()->and($v2->isSubmittedForApproval())->toBeFalse();
    Mail::assertSent(TrialLineConfigurationReturnedMail::class, fn ($m) => $m->hasTo($maker->email) && $m->returnedByStage === 'Approved (PIE Leader)' && $m->reason === $reason);

    $this->actingAs($maker)->put(route('trials.line-configuration.update', $trial->id), lcrPayload(['intent' => 'draft', 'client_name' => 'Revised']))->assertRedirect();
    $this->actingAs($maker)->get(route('trials.report.show', $trial->id))
        ->assertInertia(fn ($p) => $p->where('lineConfigurationReturnNote.reason', $reason)->where('canCheckProdLineConfigurationReport', false));
    $this->actingAs($prod)->post(route('trials.line-configuration.check-prod', $trial->id))->assertForbidden();

    submitLcr($maker, $trial);
    $this->actingAs($prod)->post(route('trials.line-configuration.check-prod', $trial->id))->assertRedirect();
});

test('PROD can also return, and the drafter is mailed with the PROD stage label', function () {
    Mail::fake();
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);

    $this->actingAs($prod)->post(route('trials.line-configuration.return', $trial->id), [
        'reason' => 'Ini alasan pengembalian yang sengaja dibuat panjang untuk lolos validasi',
    ])->assertRedirect();

    Mail::assertSent(TrialLineConfigurationReturnedMail::class, fn ($m) => $m->hasTo($maker->email) && $m->returnedByStage === 'Acknowledged (PROD)');
});

test('a locked historical version keeps its frozen label after the live lane is renamed', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);
    $this->actingAs($prod)->post(route('trials.line-configuration.return', $trial->id), [
        'reason' => 'Data produksi belum sesuai standar mohon direvisi ulang sebelum disetujui kembali',
    ]);

    LineConfigurationLane::where('stage_key', 'checked_prod')->update(['label' => 'Checked (Line Head)']);

    $v1 = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('version', 1)->firstOrFail();
    expect($v1->is_locked)->toBeTrue()->and($v1->checked_prod_label)->toBe('Acknowledged (PROD)');
    expect(LineConfigurationLane::label('checked_prod', '?'))->toBe('Checked (Line Head)');
});

test('the report page exposes who may sign each stage', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create(['name' => 'Siti PROD']);
    $leader = makePieLeader(['name' => 'Mr DJ']);
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);

    $this->actingAs($maker)->get(route('trials.report.show', $trial->id))->assertInertia(function ($p) use ($prod, $leader) {
        $signers = $p->toArray()['props']['lineConfigurationSigners'];
        expect($signers['checked_prod'])->toContain($prod->name);
        expect($signers['approved_pie'])->toBe([$leader->name]);
    });
});

test('without an explicit leader list the approved_pie lane falls back to its team', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);
    $this->actingAs($prod)->post(route('trials.line-configuration.check-prod', $trial->id));

    expect(LineConfigurationLane::signerUserIds('approved_pie'))->toBe([]);
    $this->actingAs($maker)->post(route('trials.line-configuration.approve-pie', $trial->id))->assertRedirect();
});

test('the line configuration report PDF downloads for any version that exists', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);
    $this->actingAs($prod)->post(route('trials.line-configuration.return', $trial->id), [
        'reason' => 'Data produksi belum sesuai standar mohon direvisi ulang sebelum disetujui kembali',
    ]);

    $r = $this->actingAs($maker)->get(route('trials.line-configuration.versions.pdf', ['trial' => $trial->id, 'version' => 1]));
    $r->assertOk();
    expect($r->headers->get('Content-Type'))->toBe('application/pdf');
    $this->actingAs($maker)->get(route('trials.line-configuration.versions.pdf', ['trial' => $trial->id, 'version' => 3]))->assertNotFound();
});

test('a submit needs every field, a draft does not and never enters the chain', function () {
    Mail::fake();
    $maker = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial();

    $this->actingAs($maker)->put(route('trials.line-configuration.update', $trial->id), ['intent' => 'submit', 'client_name' => ''])
        ->assertInvalid(['client_name', 'opinion', 'pic']);
    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->exists())->toBeFalse();

    $this->actingAs($maker)->put(route('trials.line-configuration.update', $trial->id), ['intent' => 'draft', 'client_name' => 'Half'])
        ->assertRedirect()->assertInertiaFlash('toast.message', __('messages.toast.line_config_draft_saved'));
    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->isSubmittedForApproval())->toBeFalse();
    Mail::assertNothingSent();

    $this->actingAs($maker)->put(route('trials.line-configuration.update', $trial->id), lcrPayload(['intent' => 'submit']))->assertRedirect();
    expect($report->fresh()->isSubmittedForApproval())->toBeTrue();
});

test('an already-submitted report cannot be turned back into a draft, even by an admin', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $admin = User::factory()->role('Admin')->create();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);

    $this->actingAs($admin)->put(route('trials.line-configuration.update', $trial->id), ['intent' => 'draft', 'client_name' => 'Demoted'])->assertInvalid(['intent']);
    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail()->client_name)->toBe('JUV');
});

test('Need Approval lists the report for whoever may sign the active stage, in order', function () {
    $maker = User::factory()->reviewUnit('PIE')->create();
    $prod = User::factory()->create(['role' => 'Staff', 'department' => 'PROD', 'review_unit' => 'PROD']);
    $leader = makePieLeader();
    $trial = makeLcrTrial();
    submitLcr($maker, $trial);

    $this->actingAs($prod)->get(route('approvals.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->has('lineConfigItems', 1)->where('lineConfigItems.0.trial_id', $trial->id));
    $this->actingAs($leader)->get(route('approvals.index'))->assertForbidden();

    (new MarkTrialLineConfigurationReportSignOff)($trial, TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail(), 'checked_prod', $prod);

    $this->actingAs($leader)->get(route('approvals.index'))->assertOk()->assertInertia(fn ($p) => $p->has('lineConfigItems', 1));
    $this->actingAs($prod)->get(route('approvals.index'))->assertForbidden();
});

test('super admin can set explicit signers on a lane, and a bare label update keeps them', function () {
    $sa = User::factory()->create(['role' => 'Super Admin']);
    $u = User::factory()->create();
    $lane = LineConfigurationLane::where('stage_key', 'approved_pie')->firstOrFail();

    $this->actingAs($sa)->put(route('admin.lane-configuration.update', $lane), [
        'label' => 'Approved (PIE Leader)', 'required_team_id' => $lane->required_team_id,
        'sync_signers' => '1', 'signer_user_ids' => [$u->id],
    ])->assertRedirect();
    expect(LineConfigurationLane::signerUserIds('approved_pie'))->toBe([$u->id]);

    $this->actingAs($sa)->put(route('admin.lane-configuration.update', $lane), ['label' => 'Renamed', 'required_team_id' => $lane->required_team_id])->assertRedirect();
    expect(LineConfigurationLane::signerUserIds('approved_pie'))->toBe([$u->id]);
});

test('production standard needs at least one fully-filled row', function () {
    $reviewer = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial();

    // Only blank "Tambah Baris" rows: dropped, so the table counts as empty.
    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), lcrPayload([
            'production_standard' => [['line' => '', 'workers' => '', 'capacity' => '', 'remark' => '']],
        ]))
        ->assertInvalid(['production_standard']);

    // A partially-filled row is rejected cell by cell.
    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), lcrPayload([
            'production_standard' => [['line' => 'F', 'workers' => '', 'capacity' => '70', 'remark' => 'x']],
        ]))
        ->assertInvalid(['production_standard.0.workers']);

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->exists())->toBeFalse();
});

test('line configuration cells and the table itself are optional on submit', function () {
    $reviewer = User::factory()->reviewUnit('PIE')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), lcrPayload([
            'line_configuration' => [
                ['equipment' => 'SC', 'process' => '', 'worker' => '', 'trial_status' => 'Pass', 'remark' => ''],
                ['equipment' => '', 'process' => '', 'worker' => '', 'trial_status' => 'No Trial', 'remark' => ''],
            ],
        ]))
        ->assertValid();

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->line_configuration)->toHaveCount(1)
        ->and($report->line_configuration[0]['equipment'])->toBe('SC')
        ->and($report->line_configuration[0]['process'])->toBeNull();

    $html = view('pdf.partials.line-configuration-report', ['report' => $report])->render();
    expect($html)->toContain('<td>-</td>');
});
