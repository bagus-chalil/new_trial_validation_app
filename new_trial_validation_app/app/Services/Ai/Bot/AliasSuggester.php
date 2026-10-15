<?php

namespace App\Services\Ai\Bot;

use App\Models\AiBotAlias;

/**
 * Suggests which intent an unanswered question probably meant, by fuzzy-matching
 * its words against every built-in keyword and every taught phrase. It only
 * suggests: an admin still approves, nothing is learned silently.
 */
class AliasSuggester
{
    public const THRESHOLD = 0.6;

    /** Intents never suggested: "help" would match too loosely, the rest need a trial code. */
    private const EXCLUDED = ['help'];

    public function __construct(private readonly IntentRecognizer $recognizer = new IntentRecognizer) {}

    /**
     * @param  array{phrases?: array<string, array<int, string>>, status?: array<string, array<int, string>>}  $custom
     * @return array<int, array{intent:string, score:float, keyword:string}> best first, at most $max
     */
    public function suggest(string $question, array $custom = [], int $max = 3): array
    {
        $questionTokens = FuzzyText::tokens($question);
        if ($questionTokens === []) {
            return [];
        }

        $keywords = $this->recognizer->keywords();
        foreach ($custom['phrases'] ?? [] as $intent => $phrases) {
            $keywords[$intent] = [...($keywords[$intent] ?? []), ...$phrases];
        }
        foreach ($custom['status'] ?? [] as $phrases) {
            $keywords[IntentRecognizer::INTENT_TRIALS_BY_STATUS] = [...($keywords[IntentRecognizer::INTENT_TRIALS_BY_STATUS] ?? []), ...$phrases];
        }

        $found = [];
        foreach ($keywords as $intent => $words) {
            if (! in_array($intent, AiBotAlias::TARGETS, true) || in_array($intent, self::EXCLUDED, true)) {
                continue;
            }

            $best = ['intent' => $intent, 'score' => 0.0, 'keyword' => ''];
            foreach ($words as $word) {
                $score = FuzzyText::coverage(FuzzyText::tokens($word), $questionTokens);
                if ($score > $best['score']) {
                    $best = ['intent' => $intent, 'score' => $score, 'keyword' => trim($word)];
                }
            }

            if ($best['score'] >= self::THRESHOLD) {
                $found[] = $best;
            }
        }

        usort($found, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($found, 0, $max);
    }
}
