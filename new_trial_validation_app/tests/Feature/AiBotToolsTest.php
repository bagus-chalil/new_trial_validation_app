<?php

use App\Models\AiBotInteraction;
use App\Models\AiLesson;
use App\Models\Trial;
use App\Models\User;
use App\Services\Ai\Bot\BotInteractionLogger;
use App\Services\Ai\Bot\TrialBotTools;
use Illuminate\Support\Carbon;

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
