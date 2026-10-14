<?php

namespace App\Services\Ai;

use App\Models\AiLesson;
use Illuminate\Support\Carbon;

/**
 * Shared persistence for every learner: upsert what the run derived, bump the
 * version only on a real change, and auto-revoke lessons the data no longer
 * supports. Learners only compute; this decides what gets stored.
 */
class LessonStore
{
    /**
     * @param  array<string, array{payload: array<string, mixed>, sample_size: int, confidence: string}>  $items  keyed by scope_key
     * @return array{created:int, updated:int, unchanged:int, revoked:int}
     */
    public function sync(string $kind, array $items): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'revoked' => 0];
        $now = Carbon::now();

        $existing = AiLesson::query()->where('kind', $kind)->get()->keyBy('scope_key');

        foreach ($items as $key => $item) {
            $lesson = $existing->get($key);

            if ($lesson === null) {
                AiLesson::create([
                    'kind' => $kind,
                    'scope_key' => $key,
                    'payload' => $item['payload'],
                    'sample_size' => $item['sample_size'],
                    'confidence' => $item['confidence'],
                    'status' => AiLesson::STATUS_ACTIVE,
                    'version' => 1,
                    'learned_at' => $now,
                ]);
                $stats['created']++;

                continue;
            }

            // Loose compare: JSON turns 10.0 into 10, which must not count as a change.
            $unchanged = $lesson->payload == $item['payload']
                && $lesson->confidence === $item['confidence']
                && $lesson->status === AiLesson::STATUS_ACTIVE;
            if ($unchanged) {
                $stats['unchanged']++;

                continue;
            }

            $lesson->fill([
                'payload' => $item['payload'],
                'sample_size' => $item['sample_size'],
                'confidence' => $item['confidence'],
                'status' => AiLesson::STATUS_ACTIVE,
                'version' => $lesson->version + 1,
                'learned_at' => $now,
                'revoked_at' => null,
            ])->save();
            $stats['updated']++;
        }

        foreach ($existing as $key => $lesson) {
            if (isset($items[$key]) || $lesson->status !== AiLesson::STATUS_ACTIVE) {
                continue;
            }
            $lesson->fill(['status' => AiLesson::STATUS_REVOKED, 'revoked_at' => $now])->save();
            $stats['revoked']++;
        }

        return $stats;
    }
}
