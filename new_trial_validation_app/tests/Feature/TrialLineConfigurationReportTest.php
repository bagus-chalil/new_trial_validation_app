<?php

use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;

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
