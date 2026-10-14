<?php

use App\Services\Ai\Bot\BotAnswerComposer;
use App\Services\Ai\Bot\IntentRecognizer;
use Tests\TestCase;

uses(TestCase::class);

function trialRow(array $over = []): array
{
    return $over + [
        'id' => 7,
        'trial_code' => 'TRIAL-20260914-124439-QF1D',
        'product_name' => 'Juv Tube',
        'product_type' => 'Tube',
        'progress_status' => 'In Review',
        'pending_with' => 'QAC',
        'updated_at' => '2026-09-20 10:00:00',
    ];
}

test('my queue lists only non-zero buckets, or an empty message', function () {
    app()->setLocale('en');
    $composer = new BotAnswerComposer;

    $answer = $composer->compose(IntentRecognizer::INTENT_MY_QUEUE, [
        'draft' => 2, 'need_revision' => 0, 'in_progress' => 0, 'pending_reviews' => 3, 'pending_approvals' => 0,
    ]);
    expect($answer['text'])->toContain('2 Draft')->toContain('3 review')->not->toContain('Need Revision');

    $empty = $composer->compose(IntentRecognizer::INTENT_MY_QUEUE, [
        'draft' => 0, 'need_revision' => 0, 'in_progress' => 0, 'pending_reviews' => 0, 'pending_approvals' => 0,
    ]);
    expect($empty['text'])->toBe('Nothing is waiting for you right now.');
});

test('trials by status reports the total, the filter and links the listed trials', function () {
    app()->setLocale('id');

    $answer = (new BotAnswerComposer)->compose(IntentRecognizer::INTENT_TRIALS_BY_STATUS, [
        'status' => 'In Review', 'product_type' => 'Tube', 'total' => 9, 'trials' => [trialRow()],
    ]);

    expect($answer['text'])->toContain('Ada 9 trial')->toContain('Tube')->toContain('TRIAL-20260914-124439-QF1D')
        ->and($answer['trials'])->toBe([['id' => 7, 'trial_code' => 'TRIAL-20260914-124439-QF1D']]);

    $none = (new BotAnswerComposer)->compose(IntentRecognizer::INTENT_TRIALS_BY_STATUS, [
        'status' => null, 'product_type' => null, 'total' => 0, 'trials' => [],
    ]);
    expect($none['text'])->toStartWith('Tidak ada trial');
});

test('trial status words found and not-found answers, never hinting at why', function () {
    app()->setLocale('en');
    $composer = new BotAnswerComposer;

    $found = $composer->compose(IntentRecognizer::INTENT_TRIAL_STATUS, trialRow() + ['current_step' => 'Review', 'revision_no' => 1, 'final_decision' => null]);
    expect($found['text'])->toContain('status')->toContain('waiting for QAC')->toContain('Revision no. 1')
        ->and($found['trials'])->toHaveCount(1);

    $missing = $composer->compose(IntentRecognizer::INTENT_TRIAL_STATUS, null, ['trial_code' => 'TRIAL-20260101-000000']);
    expect($missing['text'])->toBe('Trial TRIAL-20260101-000000 was not found or you do not have access to it.');
});

test('stuck trials and hotspots format rows', function () {
    app()->setLocale('en');
    $composer = new BotAnswerComposer;

    $stuck = $composer->compose(IntentRecognizer::INTENT_STUCK_TRIALS, ['days' => 14, 'total' => 1, 'trials' => [trialRow(['pending_with' => null])]]);
    expect($stuck['text'])->toContain('more than 14 days')->toContain('no assignee yet');

    $hot = $composer->compose(IntentRecognizer::INTENT_NOT_OK_HOTSPOTS, ['product_type' => null, 'parameters' => [[
        'product_type' => 'Jar', 'parameter_name' => 'Appearance', 'not_ok_trials' => 4, 'n_trials' => 10, 'not_ok_rate' => 0.4, 'confidence' => 'sedang',
    ]]]);
    expect($hot['text'])->toContain('Jar / Appearance: 4 of 10 trials (40%)');

    $none = $composer->compose(IntentRecognizer::INTENT_NOT_OK_HOTSPOTS, ['product_type' => null, 'parameters' => []]);
    expect($none['text'])->toContain('at least 5 trials');
});

test('weighing norm answers found, not learned, and missing type', function () {
    app()->setLocale('en');
    $composer = new BotAnswerComposer;

    $found = $composer->compose(IntentRecognizer::INTENT_WEIGHING_NORM, [
        'product_type' => 'Tube', 'section' => 'Packaging', 'median_cv' => 0.006, 'high_cv' => 0.0126, 'n_trials' => 26, 'confidence' => 'tinggi',
    ], ['product_type' => 'Tube', 'section' => 'Packaging']);
    expect($found['text'])->toContain('about 0.6%')->toContain('Above 1.26%')->toContain('26 trials')->toContain('confidence high');

    $none = $composer->compose(IntentRecognizer::INTENT_WEIGHING_NORM, null, ['product_type' => 'Mixing', 'section' => 'Filling']);
    expect($none['text'])->toContain('no reliable weighing norm for Mixing Filling');

    $needType = $composer->compose(IntentRecognizer::INTENT_WEIGHING_NORM, null, ['product_type' => null, 'section' => null]);
    expect($needType['text'])->toContain('product type and section');
});

test('unknown questions get example suggestions, one per suggestable intent', function (string $locale, string $needle) {
    app()->setLocale($locale);

    $answer = (new BotAnswerComposer)->compose(IntentRecognizer::INTENT_UNKNOWN);

    expect($answer['text'])->toContain($needle)
        ->and($answer['suggestions'])->toHaveCount(count((new IntentRecognizer)->suggestableIntents()))
        ->and($answer['trials'])->toBe([]);
})->with([
    ['id', 'belum mengerti'],
    ['en', 'did not understand'],
    ['ko', '이해하지 못했습니다'],
]);

test('every answer renders in Korean without leaking raw translation keys', function () {
    app()->setLocale('ko');
    $composer = new BotAnswerComposer;

    $texts = [
        $composer->compose(IntentRecognizer::INTENT_MY_QUEUE, ['draft' => 1])['text'],
        $composer->compose(IntentRecognizer::INTENT_TRIAL_STATUS, null, ['trial_code' => 'T'])['text'],
        $composer->compose(IntentRecognizer::INTENT_STUCK_TRIALS, ['days' => 7, 'total' => 0, 'trials' => []])['text'],
        $composer->compose(IntentRecognizer::INTENT_WEIGHING_NORM, null, [])['text'],
    ];

    foreach ($texts as $text) {
        expect($text)->not->toContain('ai.');
    }
});
