<?php

namespace App\Services\Vision;

/**
 * Result of the Startup AI cross-check for ONE photo. Advisory only: it is shown next to the
 * photo and logged to ipc_logs, it never blocks the operator and never fills a checklist
 * toggle. `items` is the per-value comparison (label, expected, read, verdict, note).
 */
class StartupEvaluation
{
    /** @param list<array{key: string, label: string, expected: ?string, read: ?string, verdict: string, note: ?string}> $items */
    public function __construct(
        public readonly string $decision,
        public readonly string $reason,
        public readonly array $items,
        public readonly ?string $readSummary = null,
        public readonly ?string $expectedSummary = null,
    ) {}
}
