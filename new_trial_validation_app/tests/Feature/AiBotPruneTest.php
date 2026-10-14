<?php

use App\Models\AiBotInteraction;
use Illuminate\Support\Carbon;

function botRow(int $daysOld, ?int $feedback = null): AiBotInteraction
{
    $row = AiBotInteraction::create([
        'locale' => 'id',
        'question' => 'q',
        'matched' => true,
        'feedback' => $feedback,
    ]);
    $row->created_at = Carbon::now()->subDays($daysOld);
    $row->save();

    return $row;
}

test('prune deletes old unrated rows but keeps recent and rated ones', function () {
    $old = botRow(100);
    $recent = botRow(10);
    $oldRated = botRow(200, -1);

    $this->artisan('ai:bot-prune')->assertSuccessful();

    expect(AiBotInteraction::find($old->id))->toBeNull()
        ->and(AiBotInteraction::find($recent->id))->not->toBeNull()
        ->and(AiBotInteraction::find($oldRated->id))->not->toBeNull();
});

test('dry run deletes nothing', function () {
    $old = botRow(100);

    $this->artisan('ai:bot-prune --dry-run')->assertSuccessful();

    expect(AiBotInteraction::find($old->id))->not->toBeNull();
});

test('days option is respected and validated', function () {
    $row = botRow(40);

    $this->artisan('ai:bot-prune --days=30')->assertSuccessful();
    expect(AiBotInteraction::find($row->id))->toBeNull();

    $this->artisan('ai:bot-prune --days=0')->assertFailed();
});
