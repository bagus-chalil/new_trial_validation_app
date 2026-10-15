<?php

use App\Models\AiBotInteraction;
use App\Models\AiLesson;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use App\Models\ValidationParameter;
use App\Services\Ai\Bot\BotInteractionLogger;
use App\Services\Ai\Bot\TrialBotTools;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function botTrial(array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-BOT-'.uniqid(),
        'product_name' => 'Bot Product',
        'product_type' => 'Tube',
        'progress_status' => 'Approved',
        'current_step' => 'Review',
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));
}

test('trialStatus never reveals a draft that belongs to someone else', function () {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);
    $draft = botTrial(['progress_status' => 'Draft', 'created_by' => 'owner@local.test']);

    $tools = new TrialBotTools;

    expect($tools->trialStatus($stranger, $draft->trial_code))->toBeNull();

    $owner = User::factory()->create(['role' => 'Staff', 'email' => 'owner@local.test']);
    expect($tools->trialStatus($owner, $draft->trial_code))
        ->not->toBeNull()
        ->and($tools->trialStatus($owner, $draft->trial_code)['progress_status'])->toBe('Draft');
});

test('trialsByStatus counts only what the user may see', function () {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);
    botTrial(['progress_status' => 'Draft', 'created_by' => 'owner@local.test']);
    botTrial(['progress_status' => 'Draft', 'created_by' => 'stranger@local.test']);

    $result = (new TrialBotTools)->trialsByStatus($stranger, 'Draft');

    expect($result['total'])->toBe(1)
        ->and($result['trials'])->toHaveCount(1);
});

test('myQueue counts the user\'s own drafts and revisions only', function () {
    $me = User::factory()->create(['role' => 'Staff', 'email' => 'me@local.test']);
    botTrial(['progress_status' => 'Draft', 'created_by' => 'me@local.test']);
    botTrial(['progress_status' => 'Need Revision', 'created_by' => 'me@local.test']);
    botTrial(['progress_status' => 'Draft', 'created_by' => 'other@local.test']);

    $queue = (new TrialBotTools)->myQueue($me);

    expect($queue['draft'])->toBe(1)
        ->and($queue['need_revision'])->toBe(1)
        ->and($queue['pending_reviews'])->toBe(0)
        ->and($queue['pending_approvals'])->toBe(0);
});

test('stuckTrials lists visible in-process trials idle for the given days, oldest first', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $old = botTrial(['progress_status' => 'In Review']);
    $recent = botTrial(['progress_status' => 'In Review']);
    $old->forceFill(['updated_at' => Carbon::now()->subDays(30)])->saveQuietly();
    $recent->forceFill(['updated_at' => Carbon::now()->subDays(2)])->saveQuietly();

    $result = (new TrialBotTools)->stuckTrials($admin, 14);

    expect($result['total'])->toBe(1)
        ->and($result['trials'][0]['trial_code'])->toBe($old->trial_code);
});

test('notOkHotspots returns only active hotspot lessons, filtered by product type', function () {
    $make = fn (string $type, string $name, bool $hotspot, string $status = 'active') => AiLesson::create([
        'kind' => AiLesson::KIND_NOT_OK_PATTERN,
        'scope_key' => $type.'|'.$name,
        'payload' => ['product_type' => $type, 'parameter_name' => $name, 'not_ok_trials' => 4, 'n_trials' => 10, 'not_ok_rate' => 0.4, 'hotspot' => $hotspot],
        'sample_size' => 10,
        'confidence' => 'sedang',
        'status' => $status,
        'version' => 1,
        'learned_at' => Carbon::now(),
    ]);
    $make('Jar', 'Appearance', true);
    $make('Jar', 'Odor', false);
    $make('Tube', 'Label', true);
    $make('Tube', 'Color', true, 'revoked');

    $tools = new TrialBotTools;

    expect($tools->notOkHotspots()['parameters'])->toHaveCount(2)
        ->and($tools->notOkHotspots('jar')['parameters'])->toHaveCount(1)
        ->and($tools->notOkHotspots('jar')['parameters'][0]['parameter_name'])->toBe('Appearance');
});

test('weighingNorm returns the learned spread or null when nothing was learned', function () {
    AiLesson::create([
        'kind' => AiLesson::KIND_WEIGHING_CONSISTENCY,
        'scope_key' => 'Tube|Packaging',
        'payload' => ['product_type' => 'Tube', 'section' => 'Packaging', 'median_cv' => 0.006, 'high_cv' => 0.0126, 'n_trials' => 26],
        'sample_size' => 26,
        'confidence' => 'sedang',
        'status' => 'active',
        'version' => 1,
        'learned_at' => Carbon::now(),
    ]);

    $tools = new TrialBotTools;

    expect($tools->weighingNorm('Tube', 'Packaging')['high_cv'])->toEqual(0.0126)
        ->and($tools->weighingNorm('Lip', 'Packaging'))->toBeNull();
});

test('the interaction logger stores a pseudonym, not the email, and ignores a double submit', function () {
    $user = User::factory()->create(['email' => 'someone@local.test']);
    $logger = new BotInteractionLogger;

    $first = $logger->record($user, 'antrean saya', 'my_queue', '2 draft', 'id');
    $again = $logger->record($user, 'antrean saya', 'my_queue', '2 draft', 'id');

    expect($again->id)->toBe($first->id)
        ->and(AiBotInteraction::count())->toBe(1)
        ->and($first->user_ref)->toStartWith('u_')
        ->and($first->user_ref)->not->toContain('someone')
        ->and($first->matched)->toBeTrue();
});

test('an unmatched question is logged as unmatched and only its author can rate it', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $logger = new BotInteractionLogger;

    $row = $logger->record($user, 'apa cuaca hari ini', null, null, 'id');

    expect($row->matched)->toBeFalse()
        ->and($logger->rate($other, $row, 1))->toBeFalse()
        ->and($logger->rate($user, $row, 5))->toBeFalse()
        ->and($logger->rate($user, $row, -1))->toBeTrue()
        ->and($row->fresh()->feedback)->toBe(-1);
});

test('myTrials lists only the asker\'s own trials and filters by status', function () {
    $me = User::factory()->create(['role' => 'Staff', 'email' => 'me@local.test']);
    botTrial(['progress_status' => 'Draft', 'created_by' => 'me@local.test']);
    botTrial(['progress_status' => 'Approved', 'created_by' => 'me@local.test']);
    botTrial(['progress_status' => 'Approved', 'created_by' => 'other@local.test']);

    $tools = new TrialBotTools;

    expect($tools->myTrials($me)['total'])->toBe(2)
        ->and($tools->myTrials($me, 'Draft')['total'])->toBe(1)
        ->and($tools->myTrials($me, 'Rejected')['total'])->toBe(0);
});

test('searchTrials matches code, product name and finish good code within visibility, and escapes wildcards', function () {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);
    botTrial(['trial_code' => 'TRIAL-SRCH-1', 'product_name' => 'Serum Vitamin C', 'finish_good_code' => 'FG-0012']);
    botTrial(['trial_code' => 'TRIAL-SRCH-2', 'product_name' => 'Cushion', 'finish_good_code' => 'FG-0099']);
    botTrial(['trial_code' => 'TRIAL-SRCH-3', 'product_name' => 'Serum Hidden Draft', 'progress_status' => 'Draft', 'created_by' => 'owner@local.test']);

    $tools = new TrialBotTools;

    expect($tools->searchTrials($stranger, 'serum')['total'])->toBe(1)
        ->and($tools->searchTrials($stranger, 'fg-0099')['trials'][0]['trial_code'])->toBe('TRIAL-SRCH-2')
        ->and($tools->searchTrials($stranger, 'TRIAL-SRCH')['total'])->toBe(2)
        ->and($tools->searchTrials($stranger, '%')['total'])->toBe(0)
        ->and($tools->searchTrials($stranger, '_')['total'])->toBe(0);
});

test('trialsByStatus honours the limit and caps it at 10', function () {
    $me = User::factory()->create(['role' => 'Staff', 'email' => 'me@local.test']);
    foreach (range(1, 12) as $i) {
        botTrial();
    }

    $tools = new TrialBotTools;

    expect($tools->trialsByStatus($me, null, null, 3)['trials'])->toHaveCount(3)
        ->and($tools->trialsByStatus($me, null, null, 99)['trials'])->toHaveCount(10);
});

test('trialNotOk lists the NOT OK parameters of a visible trial and hides a draft that is not theirs', function () {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);
    $trial = botTrial(['trial_code' => 'TRIAL-NOTOK-1']);
    $draft = botTrial(['trial_code' => 'TRIAL-NOTOK-2', 'progress_status' => 'Draft', 'created_by' => 'owner@local.test']);

    $appearance = ValidationParameter::create(['product_type' => 'Tube', 'parameter_name' => 'Appearance']);
    $odor = ValidationParameter::create(['product_type' => 'Tube', 'parameter_name' => 'Odor']);
    DB::table('trials_results')->insert([
        ['trial_id' => $trial->id, 'parameter_id' => $appearance->id, 'decision' => 'NOT OK', 'result_value' => 'Retak', 'remark' => 'Tutup retak'],
        ['trial_id' => $trial->id, 'parameter_id' => $odor->id, 'decision' => 'OK', 'result_value' => 'Conform', 'remark' => null],
        ['trial_id' => $draft->id, 'parameter_id' => $odor->id, 'decision' => 'NOT OK', 'result_value' => 'Bau', 'remark' => 'Bau'],
    ]);

    $tools = new TrialBotTools;
    $result = $tools->trialNotOk($stranger, 'TRIAL-NOTOK-1');

    expect($result['checked'])->toBe(2)
        ->and($result['not_ok'])->toHaveCount(1)
        ->and($result['not_ok'][0]['parameter_name'])->toBe('Appearance')
        ->and($result['not_ok'][0]['remark'])->toBe('Tutup retak')
        ->and($tools->trialNotOk($stranger, 'TRIAL-NOTOK-2'))->toBeNull()
        ->and($tools->trialNotOk($stranger, 'TRIAL-DOES-NOT-EXIST'))->toBeNull();
});

test('trialReviews reports the current round per department and hides trials the user cannot open', function () {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);
    $reviewer = User::factory()->reviewUnit('QAC')->create(['name' => 'Rina QAC']);
    $trial = botTrial(['trial_code' => 'TRIAL-REV-1', 'progress_status' => 'In Review']);
    $draft = botTrial(['trial_code' => 'TRIAL-REV-2', 'progress_status' => 'Draft', 'created_by' => 'owner@local.test']);

    TrialReview::create(['trial_id' => $trial->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Reviewed', 'reviewer_user_id' => $reviewer->id, 'reviewed_at' => '2026-10-01 10:00:00']);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'RNI', 'review_round' => 1, 'status' => 'Pending']);
    TrialReview::create(['trial_id' => $draft->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Pending']);

    $tools = new TrialBotTools;
    $result = $tools->trialReviews($stranger, 'TRIAL-REV-1');
    $byDept = collect($result['reviews'])->keyBy('department');

    expect($result['reviews'])->toHaveCount(2)
        ->and($byDept['QAC']['status'])->toBe('Reviewed')
        ->and($byDept['QAC']['reviewer'])->toBe('Rina QAC')
        ->and($byDept['RNI']['status'])->toBe('Pending')
        ->and($tools->trialReviews($stranger, 'TRIAL-REV-2'))->toBeNull();
});

test('stepDuration returns active lessons filtered by stage, team and product type', function () {
    $make = fn (string $metric, string $scope, string $status = 'active') => AiLesson::create([
        'kind' => AiLesson::KIND_STEP_DURATION,
        'scope_key' => $metric.'|'.$scope,
        'payload' => ['metric' => $metric, 'scope' => $scope, 'typical_hours' => 24.0, 'slow_hours' => 96.0, 'n' => 12],
        'sample_size' => 12,
        'confidence' => 'sedang',
        'status' => $status,
        'version' => 1,
        'learned_at' => Carbon::now(),
    ]);
    $make('prepare', 'Tube');
    $make('prepare', 'Jar');
    $make('review', 'QAC');
    $make('review', 'RNI');
    $make('approval', 'all');
    $make('approval', 'old', 'revoked');

    $tools = new TrialBotTools;

    expect($tools->stepDuration()['durations'])->toHaveCount(5)
        ->and($tools->stepDuration('review')['durations'])->toHaveCount(2)
        ->and($tools->stepDuration('review', 'qac')['durations'])->toHaveCount(1)
        ->and($tools->stepDuration('prepare', null, 'tube')['durations'])->toHaveCount(1)
        ->and($tools->stepDuration('approval')['durations'][0]['typical_hours'])->toBe(24.0);
});
