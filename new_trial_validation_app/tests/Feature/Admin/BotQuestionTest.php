<?php

use App\Models\AiBotAlias;
use App\Models\AiBotInteraction;
use App\Models\User;

function adminBotRow(array $attributes = []): AiBotInteraction
{
    return AiBotInteraction::create(array_merge([
        'user_ref' => 'u_abc',
        'locale' => 'id',
        'question' => 'cuaca besok',
        'matched' => false,
    ], $attributes));
}

test('only admins can open the bot questions page', function () {
    $this->actingAs(User::factory()->create(['role' => 'Staff']))
        ->get(route('admin.bot-questions.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'Admin']))
        ->get(route('admin.bot-questions.index'))
        ->assertOk();
});

test('unanswered view groups identical questions and counts them', function () {
    adminBotRow(['question' => 'Cuaca besok']);
    adminBotRow(['question' => 'cuaca besok']);
    adminBotRow(['question' => 'harga bahan']);
    adminBotRow(['question' => 'tugas saya', 'matched' => true, 'intent' => 'my_queue']);

    $this->actingAs(User::factory()->create(['role' => 'Admin']))
        ->get(route('admin.bot-questions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('view', 'unanswered')
            ->where('rows.total', 2)
            ->where('rows.data.0.asked_count', 2)
            ->where('stats.total', 4)
            ->where('stats.unmatched', 3));
});

test('downvoted view lists only thumbs-down answers', function () {
    adminBotRow(['question' => 'a', 'matched' => true, 'feedback' => -1]);
    adminBotRow(['question' => 'b', 'matched' => true, 'feedback' => 1]);

    $this->actingAs(User::factory()->create(['role' => 'Admin']))
        ->get(route('admin.bot-questions.index', ['view' => 'downvoted']))
        ->assertInertia(fn ($page) => $page
            ->where('rows.total', 1)
            ->where('rows.data.0.question', 'a')
            ->where('stats.upvoted', 1)
            ->where('stats.downvoted', 1));
});

test('an admin can teach the bot a phrase and the bot then understands it', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $staff = User::factory()->create(['role' => 'Staff']);

    $before = $this->actingAs($staff)->postJson(route('bot.ask'), ['question' => 'trial yang butuh persetujuan?'])->json();
    expect($before['intent'])->toBe('unknown');

    $this->actingAs($admin)->post(route('admin.bot-questions.aliases.store'), [
        'phrase' => 'Butuh Persetujuan ?',
        'target' => 'trials_by_status',
        'status' => 'Ready for Approval',
    ])->assertRedirect();

    expect(AiBotAlias::firstOrFail())->phrase->toBe('butuh persetujuan')->status->toBe('Ready for Approval');

    $after = $this->actingAs($staff)->postJson(route('bot.ask'), ['question' => 'trial yang butuh persetujuan?'])->json();
    expect($after['intent'])->toBe('trials_by_status');
});

test('a taught or ignored question disappears from the unanswered list', function () {
    adminBotRow(['question' => 'butuh persetujuan']);
    adminBotRow(['question' => 'next']);
    adminBotRow(['question' => 'harga bahan']);
    $admin = User::factory()->create(['role' => 'Admin']);

    $this->actingAs($admin)->post(route('admin.bot-questions.aliases.store'), ['phrase' => 'butuh persetujuan', 'target' => 'trials_by_status', 'status' => 'Ready for Approval']);
    $this->actingAs($admin)->post(route('admin.bot-questions.aliases.store'), ['phrase' => 'next', 'target' => 'ignore']);

    $this->actingAs($admin)->get(route('admin.bot-questions.index'))
        ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.question', 'harga bahan'));
});

test('alias input is validated and limited to fixed intents', function () {
    $admin = User::factory()->create(['role' => 'Admin']);

    $this->actingAs($admin)->post(route('admin.bot-questions.aliases.store'), ['phrase' => 'ab', 'target' => 'my_queue'])
        ->assertSessionHasErrors('phrase');
    $this->actingAs($admin)->post(route('admin.bot-questions.aliases.store'), ['phrase' => 'something', 'target' => 'drop_tables'])
        ->assertSessionHasErrors('target');
    expect(AiBotAlias::count())->toBe(0);
});

test('only admins can manage bot aliases', function () {
    $staff = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($staff)->post(route('admin.bot-questions.aliases.store'), ['phrase' => 'tugas', 'target' => 'my_queue'])->assertForbidden();

    $alias = AiBotAlias::create(['phrase' => 'tugas', 'target' => 'my_queue']);
    $this->actingAs($staff)->delete(route('admin.bot-questions.aliases.destroy', $alias))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'Admin']))->delete(route('admin.bot-questions.aliases.destroy', $alias))->assertRedirect();
    expect(AiBotAlias::count())->toBe(0);
});

test('the help intent explains what the bot can do', function () {
    $staff = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($staff)->postJson(route('bot.ask'), ['question' => 'kamu bisa apa?'])->json();

    expect($response['intent'])->toBe('help')->and($response['suggestions'])->not->toBeEmpty();
});
