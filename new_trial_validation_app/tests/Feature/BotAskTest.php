<?php

use App\Models\AiBotInteraction;
use App\Models\Trial;
use App\Models\User;

function askTrial(array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-20260101-000000-ASK1',
        'product_name' => 'Ask Product',
        'product_type' => 'Tube',
        'progress_status' => 'Draft',
        'current_step' => 'Validation',
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));
}

test('guests cannot ask the bot', function () {
    $this->postJson(route('bot.ask'), ['question' => 'tugas saya'])->assertUnauthorized();
});

test('a question is required and capped at 500 chars', function () {
    $user = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($user)->postJson(route('bot.ask'), [])->assertJsonValidationErrors('question');
    $this->actingAs($user)->postJson(route('bot.ask'), ['question' => str_repeat('a', 501)])->assertJsonValidationErrors('question');
});

test('a recognized question is answered and logged as matched', function () {
    $user = User::factory()->create(['role' => 'Staff', 'email' => 'owner@local.test']);
    askTrial();

    $response = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'Apa status TRIAL-20260101-000000-ASK1?'])
        ->assertOk()
        ->assertJsonPath('intent', 'trial_status')
        ->assertJsonPath('trials.0.trial_code', 'TRIAL-20260101-000000-ASK1');

    $row = AiBotInteraction::findOrFail($response->json('id'));
    expect($row->matched)->toBeTrue()->and($row->intent)->toBe('trial_status');
});

test('a trial the user cannot see gets the same answer as a missing one', function () {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);
    askTrial();

    $hidden = $this->actingAs($stranger)->postJson(route('bot.ask'), ['question' => 'status TRIAL-20260101-000000-ASK1'])->json();
    $missing = $this->actingAs($stranger)->postJson(route('bot.ask'), ['question' => 'status TRIAL-20260101-000000-ZZZ9'])->json();

    expect($hidden['trials'])->toBe([])
        ->and(str_replace('ASK1', 'X', $hidden['text']))->toBe(str_replace('ZZZ9', 'X', $missing['text']));
});

test('an unknown question returns suggestions and is logged as unmatched', function () {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'blah blah xyz'])
        ->assertOk()
        ->assertJsonPath('intent', 'unknown');

    expect($response->json('suggestions'))->not->toBeEmpty();
    $row = AiBotInteraction::findOrFail($response->json('id'));
    expect($row->matched)->toBeFalse()->and($row->intent)->toBeNull();
});

test('the endpoint is rate limited', function () {
    $user = User::factory()->create(['role' => 'Staff']);

    foreach (range(1, 20) as $i) {
        $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'q'.$i])->assertOk();
    }
    $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'one more'])->assertStatus(429);
});

test('a user can rate their own answer', function () {
    $user = User::factory()->create(['role' => 'Staff']);
    $id = $this->actingAs($user)->postJson(route('bot.ask'), ['question' => 'blah blah xyz'])->json('id');

    $this->actingAs($user)->postJson(route('bot.rate', $id), ['feedback' => -1])->assertOk();

    expect(AiBotInteraction::findOrFail($id))->feedback->toBe(-1);
});

test('rating rejects bad values, other users and guests', function () {
    $owner = User::factory()->create(['role' => 'Staff']);
    $other = User::factory()->create(['role' => 'Staff']);
    $id = $this->actingAs($owner)->postJson(route('bot.ask'), ['question' => 'blah blah xyz'])->json('id');

    $this->actingAs($owner)->postJson(route('bot.rate', $id), ['feedback' => 5])->assertJsonValidationErrors('feedback');
    $this->actingAs($other)->postJson(route('bot.rate', $id), ['feedback' => 1])->assertNotFound();
    expect(AiBotInteraction::findOrFail($id)->feedback)->toBeNull();

    auth()->logout();
    $this->postJson(route('bot.rate', $id), ['feedback' => 1])->assertUnauthorized();
});
