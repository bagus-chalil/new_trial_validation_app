<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin consumer of the Vision Service's typed text insights (`POST /api/insights/summarize`).
 *
 * The Vision Service is the single AI provider: it owns the prompt, the writing rules and the output
 * check (for decision-related kinds it refuses an answer that recommends approve/reject/hold). This
 * app sends only a `kind`, the facts it computed itself and the language, never a prompt. Returns a
 * result array instead of throwing, so the caller can always fall back to its rule-based points.
 */
class InsightClient
{
    public const KIND_TRIAL_SUMMARY = 'trial_summary';

    public const ERR_UNREACHABLE = 'unreachable';

    public const ERR_OUTDATED = 'outdated';

    public const ERR_REJECTED = 'rejected';

    public const ERR_FAILED = 'failed';

    /**
     * @param  list<string>  $facts  plain, already-anonymised sentences
     * @param  string  $language  id|en|ko, the language of the written paragraph
     * @return array{ok: true, text: string}|array{ok: false, code: string}
     */
    public function summarize(string $kind, array $facts, string $language = 'id'): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('ai.insights.base_url'), '/'))
                ->timeout((int) config('ai.insights.timeout'))
                ->connectTimeout((int) config('ai.insights.connect_timeout'))
                ->acceptJson()
                ->post('/api/insights/summarize', ['kind' => $kind, 'facts' => $facts, 'language' => $language]);
        } catch (ConnectionException) {
            return ['ok' => false, 'code' => self::ERR_UNREACHABLE];
        }

        $text = $response->json('text');

        return match (true) {
            // 400 = an older Vision Service that does not know this kind (or `language`) yet
            in_array($response->status(), [400, 404, 405], true) => ['ok' => false, 'code' => self::ERR_OUTDATED],
            $response->status() === 422 && $response->json('status') === 'ERROR' => ['ok' => false, 'code' => self::ERR_REJECTED],
            ! $response->successful() || ! is_string($text) || trim($text) === '' => ['ok' => false, 'code' => self::ERR_FAILED],
            default => ['ok' => true, 'text' => trim($text)],
        };
    }
}
