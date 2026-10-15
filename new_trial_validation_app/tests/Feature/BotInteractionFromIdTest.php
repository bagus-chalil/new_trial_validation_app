<?php

use App\Models\AiBotAlias;
use App\Models\AiBotInteraction;
use Illuminate\Support\Facades\Schema;

test('ai_bot_interactions has a nullable from_id column that can be stored', function () {
    expect(Schema::hasColumn('ai_bot_interactions', 'from_id'))->toBeTrue();

    $row = AiBotInteraction::create(['question' => 'x', 'matched' => false, 'from_id' => 7]);

    expect($row->fresh()->from_id)->toBe(7);
});

test('new intents are teachable targets', function () {
    expect(AiBotAlias::TARGETS)->toContain('summary', 'my_trials', 'latest_trials', 'step_duration', 'how_to');

    foreach (['id', 'en', 'ko'] as $locale) {
        foreach (AiBotAlias::TARGETS as $target) {
            expect(trans("admin.bot_questions.targets.$target", [], $locale))->not->toBe("admin.bot_questions.targets.$target");
        }
    }
});
