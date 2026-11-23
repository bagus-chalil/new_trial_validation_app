<?php

namespace App\Services\Ai;

use App\Models\Trial;
use Illuminate\Support\Facades\Cache;

/**
 * "Tulis ringkasan AI" for one trial. Consumer only: the rule-based facts of TrialSummaryBuilder go
 * to the Vision Service (kind `trial_summary`), which owns the prompt and refuses an answer that
 * recommends approve/reject/hold. The points stay visible next to the paragraph either way, and
 * nothing here changes the trial.
 */
class TrialSummaryAnalyzer
{
    private const CACHE_SECONDS = 1800;

    public function __construct(
        private readonly TrialSummaryBuilder $builder,
        private readonly InsightClient $insights,
    ) {}

    /**
     * @return array{ok: bool, text?: string, error?: string, cached?: bool}
     */
    public function analyze(Trial $trial, string $locale): array
    {
        $facts = array_column($this->builder->build($trial), 'text');
        // The facts already carry the trial's current state, so a change in the trial changes the key.
        $key = 'trial-summary-ai:'.sha1($locale."\n".implode("\n", $facts));

        if (($cached = Cache::get($key)) !== null) {
            return ['ok' => true, 'text' => $cached, 'cached' => true];
        }

        $result = $this->insights->summarize(InsightClient::KIND_TRIAL_SUMMARY, $facts, $locale);

        if (! $result['ok']) {
            return ['ok' => false, 'error' => __('insight.errors.'.$result['code'])];
        }

        Cache::put($key, $result['text'], self::CACHE_SECONDS);

        return ['ok' => true, 'text' => $result['text']];
    }
}
