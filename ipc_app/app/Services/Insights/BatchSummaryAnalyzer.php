<?php

namespace App\Services\Insights;

use App\Models\IpcBatch;
use App\Services\Vision\InsightClient;
use Illuminate\Support\Facades\Cache;

/**
 * "Tulis ringkasan AI" for the approver. This app is only a consumer: it sends the rule-based facts of
 * ONE batch (BatchSummaryBuilder) to the Vision Service, which owns the prompt and the rules (no
 * approve/reject recommendation, facts only) and returns a short Indonesian paragraph. The rule-based
 * points stay visible next to it either way, and nothing here ever changes a decision.
 */
class BatchSummaryAnalyzer
{
    private const CACHE_SECONDS = 1800;

    public function __construct(
        private readonly BatchSummaryBuilder $builder,
        private readonly InsightClient $insights,
    ) {}

    /** @return array{ok: bool, text?: string, error?: string, cached?: bool} */
    public function analyze(IpcBatch $batch): array
    {
        $facts = $this->builder->build($batch)['points'];
        $key = 'ipc-batch-summary-ai:'.sha1(implode("\n", $facts));

        if (($cached = Cache::get($key)) !== null) {
            return ['ok' => true, 'text' => $cached, 'cached' => true];
        }

        $result = $this->insights->summarize(InsightClient::KIND_IPC_BATCH_SUMMARY, $facts);
        if ($result['ok']) {
            Cache::put($key, $result['text'], self::CACHE_SECONDS);
        }

        return $result;
    }
}
