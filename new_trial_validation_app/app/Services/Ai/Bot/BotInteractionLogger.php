<?php

namespace App\Services\Ai\Bot;

use App\Models\AiBotInteraction;
use App\Models\User;
use App\Services\Ai\Anonymizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Writes one row per question so unanswered/downvoted questions can drive the
 * next round of intents. The user is stored as a pseudonym; the question text
 * itself is kept as typed (free text is not scrubbed, same caveat as the AI
 * export), which is why the table is pruned and admin-only.
 */
class BotInteractionLogger
{
    public function record(User $user, string $question, ?string $intent, ?string $answerSummary, string $locale): AiBotInteraction
    {
        $userRef = Anonymizer::make()->person($user->email);
        $question = Str::limit(trim($question), 500, '');

        // Duplicate guard: a double-click / retry of the same question within
        // 10 seconds is one interaction, not two (keeps the backlog honest).
        $recent = AiBotInteraction::query()
            ->where('user_ref', $userRef)
            ->where('question', $question)
            ->where('created_at', '>=', Carbon::now()->subSeconds(10))
            ->latest('id')
            ->first();
        if ($recent !== null) {
            return $recent;
        }

        return AiBotInteraction::create([
            'user_ref' => $userRef,
            'locale' => in_array($locale, ['id', 'en', 'ko'], true) ? $locale : 'id',
            'question' => $question,
            'intent' => $intent,
            'matched' => $intent !== null,
            'answer_summary' => $answerSummary === null ? null : Str::limit($answerSummary, 500, ''),
        ]);
    }

    /** Thumbs up (1) / down (-1) — only the person who asked may rate their own row. */
    public function rate(User $user, AiBotInteraction $interaction, int $feedback): bool
    {
        if ($interaction->user_ref !== Anonymizer::make()->person($user->email) || ! in_array($feedback, [1, -1], true)) {
            return false;
        }

        $interaction->feedback = $feedback;
        $interaction->feedback_at = Carbon::now();
        $interaction->save();

        return true;
    }
}
