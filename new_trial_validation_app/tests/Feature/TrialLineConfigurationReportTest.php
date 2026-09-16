<?php

use App\Mail\TrialLineConfigurationSignOffRequestedMail;
use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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

test('a PROD reviewer can view and edit the line configuration report on the report page', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
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
        ->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'Nope'])
        ->assertForbidden();
});

test('a PROD reviewer can save a line configuration report, and blank rows are dropped', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $response = $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
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
            ['no' => '1', 'equipment' => 'SC Tube 10', 'process' => 'Supply Tube', 'worker' => '1', 'trial_status' => '', 'remark' => ''],
            ['no' => '', 'equipment' => '', 'process' => '', 'worker' => '', 'trial_status' => '', 'remark' => ''],
        ],
        'opinion' => 'Other process is low risk so trial only shrink part',
    ]);

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
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial(['progress_status' => 'Approved', 'final_decision' => 'Approved']);

    TrialLineConfigurationReport::create(['trial_id' => $trial->id, 'client_name' => 'Old Client']);

    $response = $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Updated Client',
    ]);

    $response->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->client_name)->toBe('Updated Client');
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'UPDATE')->exists())->toBeTrue();
});

test('an admin can also edit the line configuration report regardless of review team', function () {
    $admin = User::factory()->role('Admin')->create();
    $trial = makeLcrTrial();

    $response = $this->actingAs($admin)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Admin Edit',
    ]);

    $response->assertRedirect(route('trials.report.show', $trial->id));
    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail()->client_name)->toBe('Admin Edit');
});

test('approved_pie/checked_prod can no longer be set directly through the general save form', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), [
            'approved_pie' => '1',
            'approved_pie_by' => 'Should Be Ignored',
            'checked_prod' => '1',
        ])
        ->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->approved_pie)->toBeFalse();
    expect($report->approved_pie_by)->toBeNull();
    expect($report->checked_prod)->toBeFalse();
});

test('assigning a new Approved(PIE) user emails them a link to the report', function () {
    Mail::fake();

    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create(['name' => 'Bagus']);
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect((int) $report->approved_pie_user_id)->toBe($pieUser->id);

    Mail::assertSent(TrialLineConfigurationSignOffRequestedMail::class, fn ($mail) => $mail->hasTo($pieUser->email) && $mail->fieldLabel === 'Approved (PIE)');
});

test('re-saving with the same assigned user does not resend the email', function () {
    Mail::fake();

    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
        'client_name' => 'Second save',
    ]);

    Mail::assertSent(TrialLineConfigurationSignOffRequestedMail::class, 1);
});

test('the assigned Approved(PIE) user can view the trial report even without any other access', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create(['role' => 'Viewer', 'department' => 'PIE']);
    $trial = makeLcrTrial(['progress_status' => 'In Review', 'final_decision' => null]);

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);

    $this->actingAs($pieUser)
        ->get(route('trials.report.show', $trial->id))
        ->assertOk();
});

test('the assigned Approved(PIE) user can confirm approval, stamping their own name and time automatically', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create(['name' => 'Bagus Approver']);
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);

    $this->actingAs($pieUser)
        ->post(route('trials.line-configuration.approve-pie', $trial->id))
        ->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->approved_pie)->toBeTrue();
    expect($report->approved_pie_by)->toBe('Bagus Approver');
    expect($report->approved_pie_at)->not->toBeNull();
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'APPROVE_PIE')->exists())->toBeTrue();
});

test('a user who is not the assigned Approved(PIE) cannot confirm approval', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $someoneElse = User::factory()->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);

    $this->actingAs($someoneElse)
        ->post(route('trials.line-configuration.approve-pie', $trial->id))
        ->assertForbidden();
});

test('the assigned Checked(PROD) user can confirm checked, stamping their own name and time automatically', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $prodUser = User::factory()->create(['name' => 'Siti PROD']);
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'checked_prod_user_id' => $prodUser->id,
    ]);

    $this->actingAs($prodUser)
        ->post(route('trials.line-configuration.check-prod', $trial->id))
        ->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->checked_prod)->toBeTrue();
    expect($report->checked_prod_by)->toBe('Siti PROD');
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'CHECK_PROD')->exists())->toBeTrue();
});

test('checking Return(PROD) for the first time immediately locks that version and spins off a new current one', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Client V1',
    ]);

    // The save that flips return_prod false→true is the Return event itself
    // — it locks immediately (capturing whatever else was submitted in the
    // same save) and a new current version is cloned forward in the same
    // request, so editing can continue without ever losing this snapshot.
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Client V1',
        'return_prod' => '1',
        'return_prod_by' => 'QC Lead',
    ]);

    $all = TrialLineConfigurationReport::where('trial_id', $trial->id)->orderBy('version')->get();
    expect($all)->toHaveCount(2);

    $v1 = $all->firstWhere('version', 1);
    $v2 = $all->firstWhere('version', 2);

    expect($v1->is_locked)->toBeTrue();
    expect($v1->locked_at)->not->toBeNull();
    expect($v1->return_prod)->toBeTrue();
    expect($v1->return_prod_by)->toBe('QC Lead');

    expect($v2->is_locked)->toBeFalse();
    expect($v2->client_name)->toBe('Client V1');
    // Sign-off state carries over onto the new current version, per direct
    // user decision — it's never force-reset.
    expect($v2->return_prod)->toBeTrue();
    expect($v2->return_prod_by)->toBe('QC Lead');

    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'NEW_VERSION')->exists())->toBeTrue();
});

test('further edits while return_prod stays checked do not spin off additional versions', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Client V1',
    ]);
    // Spins off v2 (return_prod false→true transition).
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Client V1', 'return_prod' => '1',
    ]);
    // Revising v2's own content while leaving Return checked (still true→
    // true, no transition) must not spin off a third version.
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Client V2 revised', 'return_prod' => '1',
    ]);

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->count())->toBe(2);

    $current = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->firstOrFail();
    expect($current->version)->toBe(2);
    expect($current->client_name)->toBe('Client V2 revised');
});

test('unchecking then re-checking Return(PROD) spins off another version', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'V1']);
    // false→true: spins off v2.
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'V1', 'return_prod' => '1']);
    // true→false on v2: just clears the flag, no spin-off.
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'V2', 'return_prod' => '0']);
    // false→true again on v2: a fresh Return event, spins off v3.
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'V2', 'return_prod' => '1']);

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->count())->toBe(3);
    $current = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->firstOrFail();
    expect($current->version)->toBe(3);
});

test('a locked historical version can be downloaded as a PDF but is no longer the editable current report', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'V1']);
    // Spins off v2, locking v1.
    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'V1', 'return_prod' => '1']);

    $response = $this->actingAs($reviewer)->get(route('trials.line-configuration.versions.pdf', ['trial' => $trial->id, 'version' => 1]));
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');

    // The locked v1 stays downloadable, but isn't offered as the editable
    // current row on the report page.
    $page = $this->actingAs($reviewer)->get(route('trials.report.show', $trial->id));
    $page->assertInertia(fn ($p) => $p->where('lineConfigurationReport.version', 2));
});
