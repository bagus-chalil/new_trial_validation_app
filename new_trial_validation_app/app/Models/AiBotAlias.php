<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A phrase an admin taught the trial bot from the GUI. It can only point at
 * one of the bot's fixed intents (TARGETS), so it can never inject an answer.
 *
 * @property int $id
 * @property string $phrase
 * @property string $target
 * @property string|null $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
#[Fillable(['phrase', 'target', 'status', 'created_by'])]
class AiBotAlias extends Model
{
    public const TARGET_IGNORE = 'ignore';

    /** Intents an alias may point at (plus TARGET_IGNORE). */
    public const TARGETS = ['my_queue', 'trials_by_status', 'stuck_trials', 'not_ok_hotspots', 'weighing_norm', 'help'];

    public const STATUSES = ['Draft', 'In Review', 'Ready for Approval', 'Approved', 'Need Revision', 'Rejected'];

    protected $table = 'ai_bot_aliases';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Lower-case, collapsed whitespace, no trailing "?!." — so "Untuk review ?" and "untuk review" are one phrase. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));

        return trim((string) preg_replace('/[\s?!.]+$/u', '', $text));
    }

    /**
     * Aliases shaped for IntentRecognizer::recognize(): `phrases` per intent,
     * `status` phrases per canonical status, `ignore` phrases to hide.
     *
     * @return array{phrases: array<string, array<int, string>>, status: array<string, array<int, string>>, ignore: array<int, string>}
     */
    public static function grouped(): array
    {
        $grouped = ['phrases' => [], 'status' => [], 'ignore' => []];

        foreach (static::query()->get(['phrase', 'target', 'status']) as $alias) {
            if ($alias->target === self::TARGET_IGNORE) {
                $grouped['ignore'][] = $alias->phrase;
            } elseif ($alias->target === 'trials_by_status' && $alias->status !== null) {
                $grouped['status'][$alias->status][] = $alias->phrase;
            } else {
                $grouped['phrases'][$alias->target][] = $alias->phrase;
            }
        }

        return $grouped;
    }
}
