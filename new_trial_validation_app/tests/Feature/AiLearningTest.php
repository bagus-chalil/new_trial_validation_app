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
    foreach (range(1, 4) as $_) {
        learnTrial('Tube B', [5.0, 5.1]);
    }

    $this->artisan('ai:learn')->assertSuccessful();

    expect(AiLesson::count())->toBe(0);
});

test('flagged test trials are never learned from', function () {
    foreach (range(1, 5) as $_) {
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
    foreach (range(1, 5) as $_) {
        learnTrial('Tube E', [4.0, 4.1]);
    }
    $before = [Trial::count(), TrialWeighing::count()];

    $this->artisan('ai:learn')->assertSuccessful();

    expect([Trial::count(), TrialWeighing::count()])->toBe($before);
});

test('consistency is learned per product type and is comparable across different product sizes', function () {
    // Same relative scatter (~1%) on a 10 g and an 80 g product: one shared type baseline.
    foreach (range(1, 3) as $_) {
        learnTrial('Small', [10.0, 10.1, 9.9, 10.0]);
        learnTrial('Large', [80.0, 80.8, 79.2, 80.0]);
    }

    $this->artisan('ai:learn')->assertSuccessful();

    $lesson = AiLesson::where('kind', 'weighing_consistency')->where('scope_key', 'Tube|Packaging')->firstOrFail();
    expect($lesson->payload['n_trials'])->toBe(6)
        ->and($lesson->payload['median_cv'])->toBeBetween(0.005, 0.02)
        ->and($lesson->payload['high_cv'])->toBeGreaterThan($lesson->payload['median_cv']);
});

test('consistency ignores trials with too few samples and flagged trials', function () {
    foreach (range(1, 5) as $_) {
        learnTrial('P', [10.0, 10.1, 9.9]);
    }
    learnTrial('P', [10.0, 50.0]);
    $junk = learnTrial('P', [1.0, 90.0, 5.0]);
    AiDataFlag::create(['source_table' => 'trials_header', 'source_id' => (string) $junk->id, 'field' => 'reason', 'trial_id' => $junk->id, 'flag' => AiDataFlag::SUSPECTED_TEST_TRIAL]);

    $this->artisan('ai:learn')->assertSuccessful();

    expect(AiLesson::where('kind', 'weighing_consistency')->firstOrFail()->payload['n_trials'])->toBe(5);
});

function notOkTrial(string $type, int $parameterId, string $decision): Trial
{
    $trial = Trial::create([
        'trial_code' => 'TRIAL-NOK-'.uniqid(),
        'product_name' => 'X',
        'product_type' => $type,
        'progress_status' => 'Approved',
        'current_step' => 'Review',
        'created_by' => 'owner@local.test',
        'reason' => 'Trial validasi',
    ]);
    DB::table('trials_results')->insert(['trial_id' => $trial->id, 'parameter_id' => $parameterId, 'decision' => $decision, 'result_value' => 'x']);

    return $trial;
}

function notOkParam(string $name): int
{
    return DB::table('validation_parameters')->insertGetId(['product_type' => 'Tube', 'parameter_name' => $name, 'specification' => 's', 'sort_order' => 1, 'is_active' => 1]);
}

test('not-ok pattern learns per parameter rate, ignores N/A and flags hotspots', function () {
    $param = notOkParam('Warna');
    $quiet = notOkParam('Bau');

    foreach (['NOT OK', 'NOT OK', 'NOT OK', 'OK', 'OK', 'OK', 'N/A', 'N/A'] as $d) {
        notOkTrial('Tube', $param, $d);
    }
    foreach (range(1, 6) as $_) {
        notOkTrial('Tube', $quiet, 'OK');
    }

    $this->artisan('ai:learn')->assertSuccessful();

    $hot = AiLesson::where('kind', 'not_ok_pattern')->where('scope_key', "Tube|$param")->firstOrFail();
    expect($hot->payload['n_trials'])->toBe(6)
        ->and($hot->payload['not_ok_trials'])->toBe(3)
        ->and($hot->payload['not_ok_rate'])->toEqual(0.5)
        ->and($hot->payload['hotspot'])->toBeTrue();

    $calm = AiLesson::where('kind', 'not_ok_pattern')->where('scope_key', "Tube|$quiet")->firstOrFail();
    expect($calm->payload['hotspot'])->toBeFalse()->and($calm->payload['not_ok_rate'])->toEqual(0);
});

test('not-ok pattern needs enough trials and skips flagged trials', function () {
    $param = notOkParam('Warna');
    foreach (range(1, 4) as $_) {
        notOkTrial('Tube', $param, 'OK');
    }
    $junk = notOkTrial('Tube', $param, 'NOT OK');
    AiDataFlag::create(['source_table' => 'trials_header', 'source_id' => (string) $junk->id, 'field' => 'reason', 'trial_id' => $junk->id, 'flag' => AiDataFlag::SUSPECTED_TEST_TRIAL]);

    $this->artisan('ai:learn')->assertSuccessful();

    expect(AiLesson::where('kind', 'not_ok_pattern')->count())->toBe(0);
});

test('step duration learns typical and slow hours and ignores single huge outliers in the threshold', function () {
    foreach (range(1, 6) as $i) {
        $trial = notOkTrial('Tube', 1, 'OK');
        DB::table('trials_header')->where('id', $trial->id)->update(['created_at' => '2026-01-01 08:00:00']);
        // submitted 2h after creation; one trial took a very long 500h
        $hours = $i === 6 ? 500 : 2;
        DB::table('activity_logs')->insert([
            'action' => 'SUBMIT_REVIEW', 'module' => 'TRIAL', 'record_id' => (string) $trial->id,
            'created_at' => date('Y-m-d H:i:s', strtotime('2026-01-01 08:00:00') + $hours * 3600),
        ]);
    }

    $this->artisan('ai:learn')->assertSuccessful();

    $lesson = AiLesson::where('kind', 'step_duration')->where('scope_key', 'prepare|Tube')->firstOrFail();
    expect($lesson->payload['typical_hours'])->toEqual(2.0)
        ->and($lesson->payload['n'])->toBe(6)
        ->and($lesson->payload['slow_hours'])->toBeGreaterThanOrEqual(2.0);
});

test('step duration needs enough samples and skips trials never submitted', function () {
    foreach (range(1, 4) as $_) {
        $trial = notOkTrial('Tube', 1, 'OK');
        DB::table('activity_logs')->insert(['action' => 'SUBMIT_REVIEW', 'module' => 'TRIAL', 'record_id' => (string) $trial->id, 'created_at' => now()->addHours(3)]);
    }
    notOkTrial('Tube', 1, 'OK');

    $this->artisan('ai:learn')->assertSuccessful();

    expect(AiLesson::where('kind', 'step_duration')->count())->toBe(0);
});

test('ai:status fails when nothing has run or the last run is stale, passes when fresh', function () {
    $this->artisan('ai:status')->assertFailed();

    DB::table('ai_learning_runs')->insert(['ran_at' => now()->subHours(50)]);
    $this->artisan('ai:status')->assertFailed();

    $this->artisan('ai:learn')->assertSuccessful();
    $this->artisan('ai:status')->assertSuccessful();
});
