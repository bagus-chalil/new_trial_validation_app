<?php

use App\Models\AiBotInteraction;
use App\Models\User;

function botRow(array $attributes = []): AiBotInteraction
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
    botRow(['question' => 'Cuaca besok']);
    botRow(['question' => 'cuaca besok']);
    botRow(['question' => 'harga bahan']);
    botRow(['question' => 'tugas saya', 'matched' => true, 'intent' => 'my_queue']);

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
    botRow(['question' => 'a', 'matched' => true, 'feedback' => -1]);
    botRow(['question' => 'b', 'matched' => true, 'feedback' => 1]);

    $this->actingAs(User::factory()->create(['role' => 'Admin']))
        ->get(route('admin.bot-questions.index', ['view' => 'downvoted']))
        ->assertInertia(fn ($page) => $page
            ->where('rows.total', 1)
            ->where('rows.data.0.question', 'a')
            ->where('stats.upvoted', 1)
            ->where('stats.downvoted', 1));
});
