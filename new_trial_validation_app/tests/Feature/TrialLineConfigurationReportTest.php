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

test('Checked(PROD) can only be assigned to a user on the PROD review team', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $qacUser = User::factory()->reviewUnit('QAC')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), [
            'checked_prod_user_id' => $qacUser->id,
        ])
        ->assertInvalid(['checked_prod_user_id']);

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->exists())->toBeFalse();
});

test('the report page only offers PROD-team users for the Checked(PROD) assignment combobox', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create(['name' => 'Reviewer PROD']);
    $qacUser = User::factory()->reviewUnit('QAC')->create();
    $trial = makeLcrTrial();

    $page = $this->actingAs($reviewer)->get(route('trials.report.show', $trial->id));

    $page->assertInertia(function ($p) use ($reviewer, $qacUser) {
        $prodIds = collect($p->toArray()['props']['lineConfigurationProdApprovers'])->pluck('id');
        expect($prodIds)->toContain($reviewer->id);
        expect($prodIds)->not->toContain($qacUser->id);
    });
});

test('once an approver is assigned, the report is locked for the maker until an admin overrides or it is returned', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Before submit',
        'approved_pie_user_id' => $pieUser->id,
    ]);

    // The same PROD reviewer who could freely edit a moment ago is now
    // forbidden — the report has been submitted into the approval chain.
    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'Sneaky edit'])
        ->assertForbidden();

    // Admin can still override the lock.
    $admin = User::factory()->role('Admin')->create();
    $this->actingAs($admin)
        ->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'Admin override'])
        ->assertRedirect(route('trials.report.show', $trial->id));

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail()->client_name)->toBe('Admin override');
});

test('assigning a new Approved(PIE) user emails them immediately, but a Checked(PROD) assignment is deferred until PIE approves', function () {
    Mail::fake();

    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create(['name' => 'Bagus']);
    $prodUser = User::factory()->reviewUnit('PROD')->create(['name' => 'Siti']);
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
        'checked_prod_user_id' => $prodUser->id,
    ]);

    Mail::assertSent(TrialLineConfigurationSignOffRequestedMail::class, fn ($mail) => $mail->hasTo($pieUser->email) && $mail->fieldLabel === 'Approved (PIE)');
    Mail::assertNotSent(TrialLineConfigurationSignOffRequestedMail::class, fn ($mail) => $mail->hasTo($prodUser->email));

    // PIE approves — this is exactly when PROD's turn (and email) begins.
    $this->actingAs($pieUser)->post(route('trials.line-configuration.approve-pie', $trial->id));

    Mail::assertSent(TrialLineConfigurationSignOffRequestedMail::class, fn ($mail) => $mail->hasTo($prodUser->email) && $mail->fieldLabel === 'Checked (PROD)');
});

test('re-saving with the same assigned user does not resend the email', function () {
    Mail::fake();

    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $admin = User::factory()->role('Admin')->create();
    $pieUser = User::factory()->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);
    // Report is now locked for the reviewer — use Admin (override) to save
    // again with the same assignment, proving the "no resend" behavior
    // rather than the lock itself (covered by its own test above).
    $this->actingAs($admin)->put(route('trials.line-configuration.update', $trial->id), [
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

test('Checked(PROD) cannot be confirmed until Approved(PIE) is done, even by the assigned Checked(PROD) user', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $prodUser = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
        'checked_prod_user_id' => $prodUser->id,
    ]);

    $this->actingAs($prodUser)
        ->post(route('trials.line-configuration.check-prod', $trial->id))
        ->assertForbidden();
});

test('the assigned Checked(PROD) user can confirm checked once Approved(PIE) is done, stamping their own name and time automatically', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $prodUser = User::factory()->reviewUnit('PROD')->create(['name' => 'Siti PROD']);
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
        'checked_prod_user_id' => $prodUser->id,
    ]);
    $this->actingAs($pieUser)->post(route('trials.line-configuration.approve-pie', $trial->id));

    $this->actingAs($prodUser)
        ->post(route('trials.line-configuration.check-prod', $trial->id))
        ->assertRedirect(route('trials.report.show', $trial->id));

    $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->firstOrFail();
    expect($report->checked_prod)->toBeTrue();
    expect($report->checked_prod_by)->toBe('Siti PROD');
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'CHECK_PROD')->exists())->toBeTrue();
});

test('returning requires a reason of at least 10 words', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);

    $this->actingAs($pieUser)
        ->post(route('trials.line-configuration.return', $trial->id), ['reason' => 'Terlalu singkat'])
        ->assertInvalid(['reason']);

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->count())->toBe(1);
});

test('only whoever the currently active stage is assigned to (or admin) may return', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $prodUser = User::factory()->reviewUnit('PROD')->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
        'checked_prod_user_id' => $prodUser->id,
    ]);

    // PIE's stage is active — the PROD assignee's turn hasn't come yet, so
    // they can't return it either.
    $this->actingAs($prodUser)
        ->post(route('trials.line-configuration.return', $trial->id), [
            'reason' => 'Ini alasan pengembalian yang sengaja dibuat panjang untuk lolos validasi kata',
        ])
        ->assertForbidden();

    // Once PIE approves, the stage moves to PROD — now the PROD assignee
    // can return it.
    $this->actingAs($pieUser)->post(route('trials.line-configuration.approve-pie', $trial->id));

    $this->actingAs($prodUser)
        ->post(route('trials.line-configuration.return', $trial->id), [
            'reason' => 'Ini alasan pengembalian yang sengaja dibuat panjang untuk lolos validasi kata',
        ])
        ->assertRedirect(route('trials.report.show', $trial->id));
});

test('returning locks the current version with the reason and stamped approver, and spins off a fresh version with sign-off state cleared', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create(['name' => 'Bagus Approver']);
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'Client V1',
        'approved_pie_user_id' => $pieUser->id,
    ]);

    $reason = 'Data produksi belum sesuai standar mohon direvisi ulang sebelum disetujui kembali';

    $this->actingAs($pieUser)
        ->post(route('trials.line-configuration.return', $trial->id), ['reason' => $reason])
        ->assertRedirect(route('trials.report.show', $trial->id));

    $all = TrialLineConfigurationReport::where('trial_id', $trial->id)->orderBy('version')->get();
    expect($all)->toHaveCount(2);

    $v1 = $all->firstWhere('version', 1);
    $v2 = $all->firstWhere('version', 2);

    expect($v1->is_locked)->toBeTrue();
    expect($v1->locked_at)->not->toBeNull();
    expect($v1->return_prod)->toBeTrue();
    expect($v1->return_prod_by)->toBe('Bagus Approver');
    expect($v1->return_reason)->toBe($reason);
    expect($v1->client_name)->toBe('Client V1');

    expect($v2->is_locked)->toBeFalse();
    expect($v2->client_name)->toBe('Client V1');
    // A fresh review cycle — approvals/assignments/return state all reset,
    // otherwise the new version would be immediately re-locked for the
    // maker with no chance to actually revise it.
    expect($v2->approved_pie)->toBeFalse();
    expect($v2->approved_pie_user_id)->toBeNull();
    expect($v2->return_prod)->toBeFalse();
    expect($v2->return_reason)->toBeNull();
    expect($v2->isSubmittedForApproval())->toBeFalse();

    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'RETURN')->exists())->toBeTrue();
    expect(ActivityLog::where('module', 'LINE_CONFIG')->where('action', 'NEW_VERSION')->exists())->toBeTrue();
});

test('after being returned, the maker can edit the new version freely again and sees the return reason', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'approved_pie_user_id' => $pieUser->id,
    ]);
    $this->actingAs($pieUser)->post(route('trials.line-configuration.return', $trial->id), [
        'reason' => 'Data produksi belum sesuai standar mohon direvisi ulang sebelum disetujui kembali',
    ]);

    $this->actingAs($reviewer)
        ->put(route('trials.line-configuration.update', $trial->id), ['client_name' => 'Revised'])
        ->assertRedirect(route('trials.report.show', $trial->id));

    expect(TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->firstOrFail()->client_name)->toBe('Revised');

    $page = $this->actingAs($reviewer)->get(route('trials.report.show', $trial->id));
    $page->assertInertia(fn ($p) => $p->where('lineConfigurationReturnNote.reason', 'Data produksi belum sesuai standar mohon direvisi ulang sebelum disetujui kembali'));
});

test('a locked historical version can be downloaded as a PDF but is no longer the editable current report', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $pieUser = User::factory()->create();
    $trial = makeLcrTrial();

    $this->actingAs($reviewer)->put(route('trials.line-configuration.update', $trial->id), [
        'client_name' => 'V1', 'approved_pie_user_id' => $pieUser->id,
    ]);
    $this->actingAs($pieUser)->post(route('trials.line-configuration.return', $trial->id), [
        'reason' => 'Data produksi belum sesuai standar mohon direvisi ulang sebelum disetujui kembali',
    ]);

    $response = $this->actingAs($reviewer)->get(route('trials.line-configuration.versions.pdf', ['trial' => $trial->id, 'version' => 1]));
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');

    $page = $this->actingAs($reviewer)->get(route('trials.report.show', $trial->id));
    $page->assertInertia(fn ($p) => $p->where('lineConfigurationReport.version', 2));
});
