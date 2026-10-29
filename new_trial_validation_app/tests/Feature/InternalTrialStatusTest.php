<?php

use App\Models\Trial;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['services.internal_api.token' => 'secret-token', 'services.internal_api.allowed_ips' => '127.0.0.1']);
});

function makeStatusTrial(string $fgCode, string $status, array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-'.uniqid(),
        'product_name' => 'Sample Product',
        'finish_good_code' => $fgCode,
        'product_type' => 'Tube',
        'progress_status' => $status,
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));
}

function callTrialStatus(array $headers = ['X-Internal-Token' => 'secret-token'], string $ip = '127.0.0.1')
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->getJson('/api/internal/trial-status', $headers);
}

test('a missing or wrong token is rejected', function () {
    callTrialStatus([])->assertUnauthorized();
    callTrialStatus(['X-Internal-Token' => 'nope'])->assertUnauthorized();
});

test('a caller outside the allowed IPs is rejected even with the right token', function () {
    callTrialStatus(ip: '192.168.1.50')->assertForbidden();
});

test('the endpoint is disabled when no token is configured', function () {
    config(['services.internal_api.token' => '']);

    callTrialStatus()->assertStatus(503);
});

test('approved wins over in progress, and the latest trial is reported', function () {
    makeStatusTrial('FG-APPROVED', 'In Review', ['trial_code' => 'T-OLD']);
    $done = makeStatusTrial('FG-APPROVED', 'Approved', ['trial_code' => 'T-DONE']);
    DB::table('trials_header')->where('id', $done->id)->update(['approved_at' => '2026-10-01 09:00:00']);

    $row = callTrialStatus()->assertOk()->json('data.FG-APPROVED');

    expect($row['status'])->toBe('approved')
        ->and($row['product_name'])->toBe('Sample Product')
        ->and($row['trial_count'])->toBe(2)
        ->and($row['approved_count'])->toBe(1)
        ->and($row['latest_trial_code'])->toBe('T-DONE')
        ->and($row['last_approved_at'])->not->toBeNull();
});

test('unfinished trials are in progress and expose who they wait for', function () {
    foreach (['Draft', 'In Review', 'Ready for Approval', 'Need Revision'] as $i => $status) {
        makeStatusTrial("FG-PROGRESS-$i", $status, ['pending_with' => 'QAC']);
    }

    $data = callTrialStatus()->assertOk()->json('data');

    foreach (range(0, 3) as $i) {
        expect($data["FG-PROGRESS-$i"]['status'])->toBe('in_progress');
    }
    expect($data['FG-PROGRESS-1']['pending_with'])->toBe('QAC');
});

test('a code with only rejected trials is rejected, but a rejected plus a live trial is in progress', function () {
    makeStatusTrial('FG-REJECTED', 'Rejected');
    makeStatusTrial('FG-RETRY', 'Rejected');
    makeStatusTrial('FG-RETRY', 'Draft');

    $data = callTrialStatus()->assertOk()->json('data');

    expect($data['FG-REJECTED']['status'])->toBe('rejected')
        ->and($data['FG-RETRY']['status'])->toBe('in_progress');
});

test('deleted trials and codes that cannot be matched are left out', function () {
    $gone = makeStatusTrial('FG-GONE', 'Approved');
    DB::table('trials_header')->where('id', $gone->id)->update(['deleted_at' => now()]);
    makeStatusTrial('N/A', 'Approved');
    makeStatusTrial('', 'Approved');

    expect(callTrialStatus()->assertOk()->json('data'))->toBe([]);
});
