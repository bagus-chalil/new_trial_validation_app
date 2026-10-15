<?php

use App\Services\Ai\Bot\IntentRecognizer;

$types = ['Tube', 'Lip Tint', 'Lip', 'Powder'];

test('recognizes intents across id / en / ko', function (string $question, string $intent, array $params) use ($types) {
    $result = (new IntentRecognizer)->recognize($question, $types);

    expect($result['intent'])->toBe($intent);
    foreach ($params as $key => $value) {
        expect($result['params'][$key] ?? null)->toBe($value);
    }
})->with([
    'my queue id' => ['Apa yang harus saya kerjakan?', 'my_queue', []],
    'my queue en' => ['what is in my queue', 'my_queue', []],
    'my queue ko' => ['내 작업 알려줘', 'my_queue', []],
    'status id' => ['trial yang ditolak untuk Tube', 'trials_by_status', ['status' => 'Rejected', 'product_type' => 'Tube']],
    'status en' => ['show trials need revision', 'trials_by_status', ['status' => 'Need Revision']],
    'status ko' => ['승인 대기 목록', 'trials_by_status', ['status' => 'Ready for Approval']],
    'approval is not approved' => ['siap approval berapa?', 'trials_by_status', ['status' => 'Ready for Approval']],
    'count without status' => ['berapa jumlah trial Powder', 'trials_by_status', ['status' => null, 'product_type' => 'Powder']],
    'stuck default days' => ['trial apa yang macet', 'stuck_trials', []],
    'stuck with days' => ['trial tertahan lebih dari 21 hari', 'stuck_trials', ['days' => 21]],
    'hotspots' => ['parameter apa yang paling sering gagal di Lip Tint', 'not_ok_hotspots', ['product_type' => 'Lip Tint']],
    'weighing' => ['normal sebaran timbang filling untuk Tube', 'weighing_norm', ['product_type' => 'Tube', 'section' => 'Filling']],
    'weighing en' => ['weighing spread packaging Powder', 'weighing_norm', ['product_type' => 'Powder', 'section' => 'Packaging']],
]);

test('a trial code wins over every other keyword', function () {
    $result = (new IntentRecognizer)->recognize('status trial-20260826-153659-7rbs ditolak?', ['Tube']);

    expect($result)->toBe([
        'intent' => 'trial_status',
        'params' => ['trial_code' => 'TRIAL-20260826-153659-7RBS'],
    ]);
});

test('longest product type wins and partial words do not match', function () use ($types) {
    $recognizer = new IntentRecognizer;

    expect($recognizer->recognize('draft Lip Tint', $types)['params']['product_type'])->toBe('Lip Tint')
        ->and($recognizer->recognize('draft lipstick', $types)['params']['product_type'])->toBeNull();
});

test('unrecognized or empty questions fall back to unknown', function (string $question) {
    expect((new IntentRecognizer)->recognize($question, ['Tube'])['intent'])->toBe('unknown');
})->with(['', '   ', 'halo apa kabar', 'cuaca hari ini']);

test('keywords() exposes the built-in keyword list of every keyword-driven intent', function () {
    $keywords = (new IntentRecognizer)->keywords();

    expect($keywords)->toHaveKeys(['my_queue', 'trials_by_status', 'stuck_trials', 'not_ok_hotspots', 'weighing_norm', 'help'])
        ->and($keywords['stuck_trials'])->toContain('macet')
        ->and($keywords['trials_by_status'])->toContain('ready for approval');
});
