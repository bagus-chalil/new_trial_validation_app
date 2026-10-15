<?php

use App\Models\AiBotAlias;
use App\Models\AiBotInteraction;
use App\Models\User;
use App\Services\Ai\Bot\AliasSuggester;
use App\Services\Ai\Bot\FuzzyText;
use App\Services\Ai\Bot\IntentRecognizer;
use App\Services\Ai\Bot\QuestionClusterer;

function unansweredGroup(string $question, int $count, array $ids, string $last = '2026-10-15 10:00:00'): array
{
    return ['question' => $question, 'asked_count' => $count, 'ids' => $ids, 'last_asked_at' => $last, 'locale' => 'id'];
}

test('fuzzy tokens tolerate typos, suffixes and ignore filler words', function () {
    expect(FuzzyText::tokens('Berapa TRIAL yang sedang?'))->toBe(['berapa', 'sedang']);
    expect(FuzzyText::tokenSimilarity('ringkasn', 'ringkasan'))->toBeGreaterThan(0.75);
    expect(FuzzyText::tokenSimilarity('revisinya', 'revisi'))->toBeGreaterThan(0.8);
    expect(FuzzyText::tokenSimilarity('승인을', '승인'))->toBeGreaterThan(0.8);
    expect(FuzzyText::tokenSimilarity('timbang', 'status'))->toBe(0.0);
    expect(FuzzyText::tokenSimilarity('ok', 'ol'))->toBe(0.0);
});

test('suggester maps a typo to the right intent and stays silent on gibberish', function () {
    $suggester = new AliasSuggester;

    expect($suggester->suggest('tolong ringkasn semua')[0]['intent'])->toBe('summary');
    expect($suggester->suggest('sebarn timbangan tube')[0]['intent'])->toBe('weighing_norm');
    expect($suggester->suggest('qwerty asdf zxcv'))->toBe([]);
    expect($suggester->suggest(''))->toBe([]);
});

test('suggester never proposes help or intents that need a trial code', function () {
    $intents = array_column((new AliasSuggester)->suggest('help bantuan siapa kenapa gagal status', max: 20), 'intent');

    expect($intents)->not->toContain('help', 'trial_not_ok', 'trial_reviews', 'trial_status');
});

test('suggester also learns from taught phrases', function () {
    $custom = ['phrases' => ['stuck_trials' => ['nyangkut']]];

    expect((new AliasSuggester)->suggest('ada yang nyangkut gak', $custom)[0]['intent'])->toBe('stuck_trials');
});

test('clusterer merges near-identical wordings under the most asked one', function () {
    $clusters = (new QuestionClusterer)->cluster([
        unansweredGroup('tugas sya hari ini', 1, [1]),
        unansweredGroup('Tugas saya hari ini?', 3, [2, 3, 4]),
        unansweredGroup('berat tube berapa', 2, [5, 6]),
    ]);

    expect($clusters)->toHaveCount(2);
    expect($clusters[0]['question'])->toBe('Tugas saya hari ini?');
    expect($clusters[0]['asked_count'])->toBe(4);
    expect($clusters[0]['ids'])->toEqualCanonicalizing([1, 2, 3, 4]);
    expect($clusters[0]['variants'])->toBe([['question' => 'tugas sya hari ini', 'asked_count' => 1]]);
    expect($clusters[1]['variants'])->toBe([]);
});

test('an unknown question with a near miss gets a did-you-mean answer', function () {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'tolong ringkasn semua'])->assertOk();

    expect($response->json('intent'))->toBe(IntentRecognizer::INTENT_UNKNOWN);
    expect($response->json('suggestions'))->toBe([__('ai.examples.summary')]);
    expect($response->json('text'))->toBe(__('ai.answers.unknown_did_you_mean.text'));
});

test('an unknown question with no near miss gets the full example list', function () {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'qwerty asdf zxcv'])->assertOk();

    expect($response->json('text'))->toBe(__('ai.answers.unknown.text'));
    expect(count($response->json('suggestions')))->toBeGreaterThan(5);
});

test('clicking a suggestion records from_id, but only for your own unanswered question', function () {
    $user = User::factory()->create(['role' => 'Staff']);
    $other = User::factory()->create(['role' => 'Staff']);

    $unknownId = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'tolong ringkasn semua'])->json('id');
    $foreignId = $this->actingAs($other)->postJson(route('bot.ask'), ['question' => 'qwerty asdf zxcv'])->json('id');

    $own = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => __('ai.examples.summary'), 'from' => $unknownId])->json('id');
    $stolen = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => __('ai.examples.latest_trials'), 'from' => $foreignId])->json('id');
    $missing = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => __('ai.examples.my_queue'), 'from' => 999999])->json('id');

    expect(AiBotInteraction::find($own)->from_id)->toBe($unknownId);
    expect(AiBotInteraction::find($stolen)->from_id)->toBeNull();
    expect(AiBotInteraction::find($missing)->from_id)->toBeNull();
});

test('the admin list clusters variants and prefers the clicked intent over text similarity', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $user = User::factory()->create(['role' => 'Staff']);

    $a = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'tolong ringkasn semua'])->json('id');
    $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'tolong ringkasn semuanya']);
    $this->actingAs($user)->postJson(route('bot.ask'), ['question' => __('ai.examples.latest_trials'), 'from' => $a]);

    $page = $this->actingAs($admin)->get(route('admin.bot-questions.index'))->assertOk()->inertiaProps('rows.data');

    expect($page)->toHaveCount(1);
    expect($page[0]['asked_count'])->toBe(2);
    expect($page[0]['variants'])->toHaveCount(1);
    expect($page[0]['suggestion'])->toEqual(['target' => 'latest_trials', 'clicked' => true, 'score' => 1]);
});

test('without a click the admin list falls back to the text suggestion', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    AiBotInteraction::create(['question' => 'tolong ringkasn semua', 'matched' => false, 'locale' => 'id']);

    $row = $this->actingAs($admin)->get(route('admin.bot-questions.index'))->inertiaProps('rows.data')[0];

    expect($row['suggestion']['target'])->toBe('summary')->and($row['suggestion']['clicked'])->toBeFalse();
});

test('teaching a phrase also teaches the checked variants and skips junk ones', function () {
    $admin = User::factory()->create(['role' => 'Admin']);

    $this->actingAs($admin)->post(route('admin.bot-questions.aliases.store'), [
        'phrase' => 'Ringkasn semua?',
        'target' => 'summary',
        'also' => ['ringkasn semuanya', 'ab', 'RINGKASN semua'],
    ])->assertRedirect();

    expect(AiBotAlias::query()->orderBy('phrase')->pluck('target', 'phrase')->all())
        ->toBe(['ringkasn semua' => 'summary', 'ringkasn semuanya' => 'summary']);
});
