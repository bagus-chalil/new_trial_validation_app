<?php

use App\Models\AiDataFlag;
use App\Models\AiLesson;
use App\Models\Trial;
use App\Models\TrialWeighing;
use Illuminate\Support\Facades\DB;

function learnTrial(string $product, array $weights, string $section = 'Packaging'): Trial
{
    $trial = Trial::create([
        'trial_code' => 'TRIAL-LRN-'.uniqid(),
        'product_name' => $product,
        'product_type' => 'Tube',
        'progress_status' => 'Approved',
        'current_step' => 'Review',
        'created_by' => 'owner@local.test',
        'reason' => 'Trial validasi',
    ]);
    foreach ($weights as $i => $w) {
        TrialWeighing::create(['trial_id' => $trial->id, 'section' => $section, 'item_no' => $i + 1, 'weight_value' => (string) $w]);
    }

    return $trial;
}

test('ai:learn learns a baseline and an outlier does not move it', function () {
    foreach (range(1, 6) as $i) {
        learnTrial('Tube A', [10.0, 10.1, 9.9, 10.0, $i === 1 ? 99.0 : 10.05]);
    }

    $this->artisan('ai:learn')->assertSuccessful();

    $lesson = AiLesson::where('scope_key', 'Tube A|Packaging')->firstOrFail();
    expect($lesson->status)->toBe('active')
        ->and($lesson->payload['median'])->toEqual(10.0)
        ->and($lesson->payload['high'])->toBeLessThan(11.0)
        ->and($lesson->payload['n_trials'])->toBe(6);
    expect(DB::table('ai_learning_runs')->count())->toBe(1);
});

test('too few trials gives no lesson (insufficient data)', function () {
    foreach (range(1, 4) as $i) {
        learnTrial('Tube B', [5.0, 5.1]);
    }

    $this->artisan('ai:learn')->assertSuccessful();

    expect(AiLesson::count())->toBe(0);
});

test('flagged test trials are never learned from', function () {
    foreach (range(1, 5) as $i) {
        learnTrial('Tube C', [2.0, 2.1]);
    }
    $junk = learnTrial('Tube C', [500.0, 500.0]);
    AiDataFlag::create(['source_table' => 'trials_header', 'source_id' => (string) $junk->id, 'field' => 'reason', 'trial_id' => $junk->id, 'flag' => AiDataFlag::SUSPECTED_TEST_TRIAL]);

    $this->artisan('ai:learn')->assertSuccessful();

    $lesson = AiLesson::where('scope_key', 'Tube C|Packaging')->firstOrFail();
    expect($lesson->payload['n_trials'])->toBe(5)
        ->and($lesson->payload['high'])->toBeLessThan(3.0);
});

test('a re-run is idempotent and an unsupported lesson is revoked automatically', function () {
    $trials = collect(range(1, 5))->map(fn () => learnTrial('Tube D', [3.0, 3.1, 2.9]));

    $this->artisan('ai:learn')->assertSuccessful();
    $this->artisan('ai:learn')->assertSuccessful();
    expect(AiLesson::first()->version)->toBe(1);

    DB::table('trials_header')->where('id', $trials->first()->id)->update(['deleted_at' => now()]);
    $this->artisan('ai:learn')->assertSuccessful();

    expect(AiLesson::first()->status)->toBe('revoked');
});

test('ai:learn never modifies application tables', function () {
    foreach (range(1, 5) as $i) {
        learnTrial('Tube E', [4.0, 4.1]);
    }
    $before = [Trial::count(), TrialWeighing::count()];

    $this->artisan('ai:learn')->assertSuccessful();

    expect([Trial::count(), TrialWeighing::count()])->toBe($before);
});
