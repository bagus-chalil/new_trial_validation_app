<?php

use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\TrialWeighing;
use App\Models\User;
use App\Models\ValidationParameter;
use App\Services\Ai\TrialSummaryBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function makeSummaryTrial(array $attributes = []): Trial
{
    return Trial::create([
        'trial_code' => $attributes['trial_code'] ?? 'TRIAL-SUM-1',
        'product_name' => 'Glow Tube',
        'finish_good_code' => 'FG-9',
        'product_type' => 'Tube',
        'validation_category' => 'New Product',
        'risk_level' => 'Medium',
        'progress_status' => $attributes['progress_status'] ?? 'In Review',
        'current_step' => 'Review',
        'created_by' => $attributes['created_by'] ?? 'creator.secret@local.test',
        'revision_no' => $attributes['revision_no'] ?? 0,
        'approver_user_id' => $attributes['approver_user_id'] ?? null,
    ]);
}

function addSummaryResult(Trial $trial, string $name, string $decision, ?string $result = null, ?string $remark = null, int $sort = 0): void
{
    $parameter = ValidationParameter::create([
        'product_type' => 'Tube',
        'parameter_name' => $name,
        'specification' => 'Spec '.$name,
        'sort_order' => $sort,
    ]);

    DB::table('trials_results')->insert([
        'trial_id' => $trial->id,
        'parameter_id' => $parameter->id,
        'decision' => $decision,
        'result_value' => $result,
        'remark' => $remark,
    ]);
}

/** @return list<string> */
function summaryTexts(Trial $trial): array
{
    app()->setLocale('id');

    return array_column((new TrialSummaryBuilder)->build($trial), 'text');
}

test('the summary points reflect the recorded trial data', function () {
    $trial = makeSummaryTrial();
    addSummaryResult($trial, 'Appearance', 'NOT OK', 'bintik', 'ada white spot', 1);
    addSummaryResult($trial, 'Label', 'OK', 'Conform', null, 2);
    addSummaryResult($trial, 'Vacuum', 'N/A', 'N/A', null, 3);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'PROD', 'review_round' => 1, 'status' => 'Reviewed', 'comment' => 'OK', 'reviewer_name' => 'Rahasia Orang', 'reviewed_at' => Carbon::now()]);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'RNI', 'review_round' => 1, 'status' => 'Reviewed', 'comment' => 'OK - pastikan kode coding benar', 'reviewed_at' => Carbon::now()]);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Pending']);
    TrialWeighing::create(['trial_id' => $trial->id, 'section' => 'Packaging', 'item_no' => 1, 'weight_value' => null, 'is_skipped' => true]);

    app()->setLocale('id');
    $points = (new TrialSummaryBuilder)->build($trial);
    $texts = array_column($points, 'text');
    $joined = implode("\n", $texts);

    expect($joined)
        ->toContain('TRIAL-SUM-1')->toContain('Glow Tube')->toContain('Tube')
        ->toContain('1 NOT OK')->toContain('1 dari 3')
        ->toContain('NOT OK: Appearance, hasil "bintik", catatan "ada white spot"')
        ->toContain('Catatan review RNI: pastikan kode coding benar')
        ->toContain('Review menunggu departemen: QAC')
        ->toContain('Review OK tanpa catatan tambahan dari: PROD')
        ->toContain('Weighing Packaging tidak dilakukan (skip)')
        ->toContain('Belum ada foto lampiran');

    // NOT OK and a real review note are the findings; a plain "OK" review and a skipped weighing are not.
    $findings = collect($points)->where('tone', 'finding')->pluck('text')->implode("\n");
    expect($findings)->toContain('NOT OK: Appearance')->toContain('Catatan review RNI')
        ->not->toContain('skip')->not->toContain('PROD');
});

test('the summary points never contain people or e-mail addresses', function () {
    $trial = makeSummaryTrial();
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'PROD', 'review_round' => 1, 'status' => 'Reviewed', 'comment' => 'OK', 'reviewer_name' => 'Rahasia Orang', 'reviewer_email' => 'rahasia@local.test', 'reviewed_at' => Carbon::now()]);
    $trial->forceFill(['approved_by' => 'approver.secret@local.test', 'approval_comment' => 'ok jalan'])->save();
    $trial->forceFill(['progress_status' => 'Approved'])->save();

    $joined = implode("\n", summaryTexts($trial->fresh()));

    expect($joined)->not->toContain('Rahasia')->not->toContain('@')->not->toContain('secret')
        ->toContain('Catatan keputusan approver: ok jalan');
});

test('product identity follows the ai anonymize switch', function () {
    config(['ai.anonymize_product' => true]);
    app()->setLocale('id');

    $joined = implode("\n", summaryTexts(makeSummaryTrial()));

    expect($joined)->toContain('TRIAL-SUM-1')->toContain('Tube')->not->toContain('Glow Tube');
});

test('long remarks are shortened and every fact stays within the Vision Service limit', function () {
    $trial = makeSummaryTrial();
    addSummaryResult($trial, 'Appearance', 'NOT OK', str_repeat('x', 400), str_repeat('y', 900));

    foreach (summaryTexts($trial) as $text) {
        expect(mb_strlen($text))->toBeLessThanOrEqual(500);
    }
});

test('a review comment only counts as a note when it says more than OK', function () {
    expect(TrialSummaryBuilder::noteBeyondOk('OK'))->toBe('')
        ->and(TrialSummaryBuilder::noteBeyondOk(' oke. '))->toBe('')
        ->and(TrialSummaryBuilder::noteBeyondOk('OK - will continue for masspro'))->toBe('will continue for masspro')
        ->and(TrialSummaryBuilder::noteBeyondOk('A re-trial is required'))->toBe('A re-trial is required')
        ->and(TrialSummaryBuilder::noteBeyondOk('okra is not ok'))->toBe('okra is not ok');
});

test('the report page carries the summary only for approvers and reviewers', function () {
    $trial = makeSummaryTrial();
    $manager = User::factory()->role('Manager QAC')->create();
    $reviewer = User::factory()->role('PRD')->reviewUnit('PROD')->create();
    $viewer = User::factory()->create(['email' => 'plain.staff@local.test']);

    // a reviewer sees a trial once a review is assigned to their department
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'PROD', 'review_round' => 1, 'status' => 'Pending']);

    $this->actingAs($manager)->get(route('trials.report.show', $trial))->assertOk()
        ->assertInertia(fn ($page) => $page->where('aiSummary.aiEnabled', false)->has('aiSummary.points'));
    $this->actingAs($reviewer)->get(route('trials.report.show', $trial))->assertOk()
        ->assertInertia(fn ($page) => $page->has('aiSummary.points'));
    $this->actingAs($viewer)->get(route('trials.report.show', $trial))->assertOk()
        ->assertInertia(fn ($page) => $page->where('aiSummary', null));
});

function summaryEndpointSetup(): array
{
    config(['ai.insights.enabled' => true, 'ai.insights.base_url' => 'http://vision.test']);
    Cache::flush();

    return [makeSummaryTrial(), User::factory()->role('Manager QAC')->create()];
}

test('asking for the AI summary sends only kind, facts and language', function () {
    [$trial, $manager] = summaryEndpointSetup();
    Http::fake(['vision.test/api/insights/summarize' => Http::response(['status' => 'OK', 'kind' => 'trial_summary', 'text' => 'Satu paragraf.'])]);

    $this->actingAs($manager)->postJson(route('trials.ai-summary', $trial))
        ->assertOk()->assertJson(['ok' => true, 'text' => 'Satu paragraf.']);

    Http::assertSent(function (HttpRequest $request) {
        $data = $request->data();
        $facts = implode("\n", $data['facts']);

        return $request->url() === 'http://vision.test/api/insights/summarize'
            && array_keys($data) === ['kind', 'facts', 'language']
            && $data['kind'] === 'trial_summary' && $data['language'] === 'id'
            && ! isset($data['system'], $data['prompt'])
            && str_contains($facts, 'TRIAL-SUM-1')
            && ! str_contains($facts, '@') && ! str_contains($facts, 'secret');
    });
});

test('the AI summary follows the UI language and is cached per content', function () {
    [$trial, $manager] = summaryEndpointSetup();
    Http::fake(['*' => Http::response(['status' => 'OK', 'text' => 'Summary.'])]);

    $this->withUnencryptedCookie('locale', 'en')->actingAs($manager)->post(route('trials.ai-summary', $trial), [], ['Accept' => 'application/json'])->assertOk();
    $this->withUnencryptedCookie('locale', 'en')->actingAs($manager)->post(route('trials.ai-summary', $trial), [], ['Accept' => 'application/json'])
        ->assertOk()->assertJson(['cached' => true]);

    Http::assertSentCount(1);
    Http::assertSent(function (HttpRequest $request) {
        return $request['language'] === 'en'
            && str_contains(implode("\n", $request['facts']), 'review round');
    });
});

test('the AI summary route is off by default and limited to approvers and reviewers', function () {
    $trial = makeSummaryTrial();
    $manager = User::factory()->role('Manager QAC')->create();
    $viewer = User::factory()->create(['email' => 'plain.staff@local.test']);
    Http::fake();

    $this->actingAs($manager)->postJson(route('trials.ai-summary', $trial))->assertNotFound();

    config(['ai.insights.enabled' => true]);
    $this->actingAs($viewer)->postJson(route('trials.ai-summary', $trial))->assertForbidden();

    Http::assertNothingSent();
});

test('a Vision Service failure becomes a plain message, never a crash', function (int $status, string $needle) {
    [$trial, $manager] = summaryEndpointSetup();
    Http::fake(['*' => Http::response(['status' => 'ERROR', 'error' => 'x'], $status)]);

    $this->actingAs($manager)->postJson(route('trials.ai-summary', $trial))
        ->assertOk()->assertJson(['ok' => false])
        ->assertJsonPath('error', fn (string $error) => str_contains($error, $needle));
})->with([
    'outdated service (404)' => [404, 'belum diperbarui'],
    'unknown kind on an older service (400)' => [400, 'belum diperbarui'],
    'advisory refusal (422)' => [422, 'ditolak'],
    'ollama down (503)' => [503, 'gagal'],
]);

test('an unreachable Vision Service becomes a plain message', function () {
    [$trial, $manager] = summaryEndpointSetup();
    Http::fake(['*' => fn () => throw new ConnectionException('down')]);

    $this->actingAs($manager)->postJson(route('trials.ai-summary', $trial))
        ->assertOk()->assertJson(['ok' => false])
        ->assertJsonPath('error', fn (string $error) => str_contains($error, 'tidak terjangkau'));
});
