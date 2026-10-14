<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bot\AskBotRequest;
use App\Http\Requests\Bot\RateBotRequest;
use App\Models\AiBotInteraction;
use App\Models\MasterOption;
use App\Models\User;
use App\Services\Ai\Bot\BotAnswerComposer;
use App\Services\Ai\Bot\BotInteractionLogger;
use App\Services\Ai\Bot\IntentRecognizer;
use App\Services\Ai\Bot\TrialBotTools;
use Illuminate\Http\JsonResponse;

/**
 * Rule-based trial bot: recognize the intent, fetch policy-scoped data through
 * TrialBotTools, compose a localized answer, and log the question (unmatched
 * questions are the intent backlog). No model, no network.
 */
class BotController extends Controller
{
    public function ask(
        AskBotRequest $request,
        IntentRecognizer $recognizer,
        TrialBotTools $tools,
        BotAnswerComposer $composer,
        BotInteractionLogger $logger,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $question = (string) $request->validated('question');

        $productTypes = MasterOption::query()
            ->where('type', 'product_type')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('name')
            ->all();

        $recognized = $recognizer->recognize($question, $productTypes);
        $intent = $recognized['intent'];
        $params = $recognized['params'];

        $answer = $composer->compose($intent, $this->fetch($tools, $user, $intent, $params), $params);

        $interaction = $logger->record(
            $user,
            $question,
            $intent === IntentRecognizer::INTENT_UNKNOWN ? null : $intent,
            $answer['text'],
            app()->getLocale(),
        );

        return response()->json([
            'id' => $interaction->id,
            'intent' => $intent,
            'text' => $answer['text'],
            'trials' => $answer['trials'],
            'suggestions' => $answer['suggestions'],
        ]);
    }

    /** Thumbs up/down on one's own answer; someone else's row is a 404, same as a missing one. */
    public function rate(RateBotRequest $request, AiBotInteraction $interaction, BotInteractionLogger $logger): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($logger->rate($user, $interaction, (int) $request->validated('feedback')), 404);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function fetch(TrialBotTools $tools, User $user, string $intent, array $params): ?array
    {
        return match ($intent) {
            IntentRecognizer::INTENT_MY_QUEUE => $tools->myQueue($user),
            IntentRecognizer::INTENT_TRIALS_BY_STATUS => $tools->trialsByStatus($user, $params['status'] ?? null, $params['product_type'] ?? null),
            IntentRecognizer::INTENT_TRIAL_STATUS => $tools->trialStatus($user, (string) ($params['trial_code'] ?? '')),
            IntentRecognizer::INTENT_STUCK_TRIALS => $tools->stuckTrials($user, (int) ($params['days'] ?? 14)),
            IntentRecognizer::INTENT_NOT_OK_HOTSPOTS => $tools->notOkHotspots($params['product_type'] ?? null),
            IntentRecognizer::INTENT_WEIGHING_NORM => ! empty($params['product_type']) && ! empty($params['section'])
                ? $tools->weighingNorm($params['product_type'], $params['section'])
                : null,
            default => null,
        };
    }
}
