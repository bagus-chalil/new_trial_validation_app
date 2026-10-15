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
    'summary id' => ['berikan ringkasan semua trial', 'summary', []],
    'summary en' => ['give me an overview', 'summary', []],
    'summary ko' => ['전체 트라이얼 요약', 'summary', []],
    'summary with status goes to list' => ['ringkasan trial draft', 'trials_by_status', ['status' => 'Draft']],
    'my trials id' => ['tampilkan trial saya', 'my_trials', []],
    'my trials with status' => ['trial saya yang masih draft', 'my_trials', ['status' => 'Draft']],
    'my trials en' => ['show my trials in review', 'my_trials', ['status' => 'In Review']],
    'my trials ko' => ['내 트라이얼 보여줘', 'my_trials', []],
    'my queue still wins' => ['tugas saya', 'my_queue', []],
    'latest default' => ['trial terbaru', 'latest_trials', []],
    'latest with count' => ['show the 8 latest trials', 'latest_trials', ['limit' => 8]],
    'latest limit capped' => ['50 trial terbaru', 'latest_trials', ['limit' => 10]],
    'latest with filter' => ['trial terbaru Tube yang draft', 'latest_trials', ['status' => 'Draft', 'product_type' => 'Tube']],
    'latest ko' => ['최신 트라이얼', 'latest_trials', []],
    'search id' => ['cari trial Serum Vitamin', 'search_trials', ['keyword' => 'serum vitamin']],
    'search en' => ['search for cushion please', 'search_trials', ['keyword' => 'cushion']],
    'search code fragment' => ['cari FG-0012', 'search_trials', ['keyword' => 'fg-0012']],
    'search ko' => ['Cushion 트라이얼 찾아줘', 'search_trials', ['keyword' => 'cushion']],
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
})->with(['', '   ', 'halo apa kabar', 'cuaca hari ini', 'cari', 'cari trial', 'search the']);

test('keywords() exposes the built-in keyword list of every keyword-driven intent', function () {
    $keywords = (new IntentRecognizer)->keywords();

    expect($keywords)->toHaveKeys(['my_queue', 'trials_by_status', 'stuck_trials', 'not_ok_hotspots', 'weighing_norm', 'help', 'summary', 'latest_trials', 'search_trials'])
        ->and($keywords['stuck_trials'])->toContain('macet')
        ->and($keywords['trials_by_status'])->toContain('ready for approval');
});
