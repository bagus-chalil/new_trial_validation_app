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
    'trial not ok id' => ['kenapa TRIAL-20260914-124439-QF1D NOT OK?', 'trial_not_ok', ['trial_code' => 'TRIAL-20260914-124439-QF1D']],
    'trial not ok en' => ['why did trial-20260914-124439-qf1d fail', 'trial_not_ok', ['trial_code' => 'TRIAL-20260914-124439-QF1D']],
    'trial not ok ko' => ['TRIAL-20260914-124439-QF1D 은 왜 부적합이야', 'trial_not_ok', []],
    'trial reviews id' => ['siapa yang belum review TRIAL-20260914-124439-QF1D', 'trial_reviews', ['trial_code' => 'TRIAL-20260914-124439-QF1D']],
    'trial reviews en' => ['who has not reviewed TRIAL-20260914-124439-QF1D', 'trial_reviews', []],
    'trial reviews ko' => ['TRIAL-20260914-124439-QF1D 검토자 누구야', 'trial_reviews', []],
    'trial code alone stays status' => ['TRIAL-20260914-124439-QF1D', 'trial_status', []],
    'duration review' => ['berapa lama proses review biasanya', 'step_duration', ['stage' => 'review']],
    'duration review team' => ['how long does QAC review take', 'step_duration', ['stage' => 'review', 'department' => 'QAC']],
    'duration approval' => ['berapa lama approval akhir', 'step_duration', ['stage' => 'approval']],
    'duration prepare type' => ['how long to prepare a Tube trial', 'step_duration', ['stage' => 'prepare', 'product_type' => 'Tube']],
    'duration any stage' => ['durasi tiap tahap', 'step_duration', ['stage' => null]],
    'duration ko' => ['검토는 보통 얼마나 걸려', 'step_duration', ['stage' => 'review']],
    'how to create' => ['bagaimana cara membuat trial baru', 'how_to', ['topic' => 'create_trial']],
    'how to create en' => ['how do I create a new trial?', 'how_to', ['topic' => 'create_trial']],
    'how to weighing wins over the weighing norm' => ['cara mengisi timbangan', 'how_to', ['topic' => 'weighing']],
    'how to submit' => ['how to submit for review', 'how_to', ['topic' => 'submit_review']],
    'how to do a review' => ['cara melakukan review', 'how_to', ['topic' => 'do_review']],
    'how to approve' => ['cara memberi approval', 'how_to', ['topic' => 'approve']],
    'how to ko' => ['검토하는 방법은?', 'how_to', ['topic' => 'do_review']],
    'how to without a topic' => ['cara pakai aplikasi ini', 'how_to', ['topic' => null]],
    'weak how needs a topic' => ['bagaimana validasi trial', 'how_to', ['topic' => 'validation']],
    'secara is not cara' => ['secara keseluruhan ada berapa trial', 'summary', []],
]);

test('weak how words alone do not trigger how_to', function () {
    expect((new IntentRecognizer)->recognize('bagaimana kabar ringkasan trial')['intent'])->not->toBe('how_to');
});

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
