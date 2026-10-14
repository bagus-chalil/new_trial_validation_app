<?php

use App\Models\AiDataFlag;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\TrialWeighing;
use App\Models\User;
use App\Services\Ai\Anonymizer;
use App\Services\Ai\TextQuality;
use App\Services\Ai\TrialDocumentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function aiTrial(array $attributes = []): Trial
{
    return Trial::create($attributes + [
        'trial_code' => 'TRIAL-AI-'.uniqid(),
        'product_name' => 'Sample Product',
        'product_type' => 'Tube',
        'progress_status' => 'In Review',
        'current_step' => 'Review',
        'created_by' => 'owner@local.test',
        'reason' => 'Trial untuk validasi mesin filling baru',
    ]);
}

test('text quality flags junk but keeps short legitimate answers', function (string $text, bool $junk) {
    expect(TextQuality::isJunk($text))->toBe($junk);
})->with([
    ['tes', true],
    ['sdfsd', true],
    ['xxxx', true],
    ['qwerty', true],
    ['OK', false],
    ['N/A', false],
    ['Conform', false],
    ['npd', false],
    ['QAC', false],
    ['dsfsdf', true],
    ['OK with note, handle with care', false],
    ['', false],
]);

test('anonymizer is stable, case-insensitive and does not leak the email', function () {
    $anon = new Anonymizer('secret');

    expect($anon->person('A@x.com'))->toBe($anon->person('a@x.com'))
        ->and($anon->person('a@x.com'))->toStartWith('u_')->not->toContain('x.com')
        ->and($anon->person('a@x.com'))->not->toBe($anon->person('b@x.com'))
        ->and($anon->person(null))->toBeNull();
});

test('document builder produces the contract shape, pseudonymizes people and blanks flagged junk', function () {
    $trial = aiTrial(['approval_comment' => 'Approved untuk produksi']);
    $review = TrialReview::create([
        'trial_id' => $trial->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Reviewed',
        'reviewer_email' => 'qac@local.test', 'comment' => 'sdfsd', 'edit_count' => 0,
    ]);
    TrialWeighing::create(['trial_id' => $trial->id, 'section' => 'Packaging', 'item_no' => 1, 'weight_value' => '10.0']);
    TrialWeighing::create(['trial_id' => $trial->id, 'section' => 'Packaging', 'item_no' => 2, 'weight_value' => '12.0']);

    $flag = AiDataFlag::create([
        'source_table' => 'trials_review', 'source_id' => (string) $review->id, 'field' => 'comment',
        'trial_id' => $trial->id, 'flag' => AiDataFlag::JUNK_TEXT,
    ]);

    $trial = Trial::with(TrialDocumentBuilder::relations())->find($trial->id);
    $doc = TrialDocumentBuilder::make()->build($trial, collect(), collect([$flag]));

    expect($doc['schema_version'])->toBe('1.0')
        ->and($doc['doc_id'])->toBe('trial-'.$trial->id)
        ->and($doc['trial']['created_by'])->toStartWith('u_')
        ->and($doc['reviews'][0]['reviewer'])->toStartWith('u_')
        ->and($doc['reviews'][0]['comment'])->toBeNull()
        ->and($doc['quality']['blanked_fields'])->toBe(1)
        ->and($doc['weighing']['Packaging']['n'])->toBe(2)
        ->and($doc['weighing']['Packaging']['mean'])->toBe(11.0)
        ->and($doc['weighing']['Filling']['n'])->toBe(0)
        ->and(json_encode($doc))->not->toContain('owner@local.test')->not->toContain('qac@local.test');
});

test('every key required by the JSON schema is present in a built document', function () {
    $schema = json_decode(file_get_contents(resource_path('ai/trial_document.schema.json')), true);
    $trial = Trial::with(TrialDocumentBuilder::relations())->find(aiTrial()->id);

    $doc = TrialDocumentBuilder::make()->build($trial, collect(), collect());

    foreach ($schema['required'] as $key) {
        expect($doc)->toHaveKey($key);
    }
    foreach ($schema['properties']['trial']['required'] as $key) {
        expect($doc['trial'])->toHaveKey($key);
    }
    expect(array_keys($doc))->toEqualCanonicalizing(array_keys($schema['properties']));
});

test('ai:quality-scan is a dry run by default and writes nothing', function () {
    aiTrial(['reason' => 'tes']);

    $this->artisan('ai:quality-scan')->assertSuccessful();

    expect(AiDataFlag::count())->toBe(0);
});

test('ai:quality-scan --apply flags junk idempotently without touching source rows', function () {
    $trial = aiTrial(['reason' => 'tes']);
    $before = DB::table('trials_header')->where('id', $trial->id)->first();

    $this->artisan('ai:quality-scan --apply')->assertSuccessful();
    $this->artisan('ai:quality-scan --apply')->assertSuccessful();

    expect(AiDataFlag::where('flag', AiDataFlag::SUSPECTED_TEST_TRIAL)->where('trial_id', $trial->id)->count())->toBe(1)
        ->and(DB::table('trials_header')->where('id', $trial->id)->first())->toEqual($before);
});

test('ai:export writes a manifest-backed JSONL and skips suspected test trials', function () {
    Storage::fake('local');
    $good = aiTrial();
    $test = aiTrial(['reason' => 'tes']);
    AiDataFlag::create([
        'source_table' => 'trials_header', 'source_id' => (string) $test->id, 'field' => 'reason',
        'trial_id' => $test->id, 'flag' => AiDataFlag::SUSPECTED_TEST_TRIAL,
    ]);

    $this->artisan('ai:export')->assertSuccessful();

    $files = Storage::disk('local')->allFiles('ai-export');
    $jsonl = collect($files)->first(fn ($f) => str_ends_with($f, '.jsonl'));
    $manifestPath = collect($files)->first(fn ($f) => str_contains($f, 'manifest-'));
    $lines = array_filter(explode("\n", Storage::disk('local')->get($jsonl)));
    $manifest = json_decode(Storage::disk('local')->get($manifestPath), true);

    expect($lines)->toHaveCount(1)
        ->and(json_decode(reset($lines), true)['doc_id'])->toBe('trial-'.$good->id)
        ->and($manifest['documents'])->toBe(1)
        ->and($manifest['skipped_suspected_test'])->toBe(1)
        ->and($manifest['sha256'])->toBe(hash_file('sha256', Storage::disk('local')->path($jsonl)));
});

test('ai:export never writes to application tables and ignores soft-deleted trials', function () {
    Storage::fake('local');
    $trial = aiTrial();
    $gone = aiTrial();
    $gone->deleted_at = now();
    $gone->save();
    $countBefore = [Trial::count(), User::count()];

    $this->artisan('ai:export')->assertSuccessful();

    $jsonl = collect(Storage::disk('local')->allFiles('ai-export'))->first(fn ($f) => str_ends_with($f, '.jsonl'));
    expect(array_filter(explode("\n", Storage::disk('local')->get($jsonl))))->toHaveCount(1)
        ->and([Trial::count(), User::count()])->toBe($countBefore)
        ->and($trial->fresh()->reason)->not->toBeNull();
});
