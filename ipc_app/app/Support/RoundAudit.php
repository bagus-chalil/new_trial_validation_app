<?php

namespace App\Support;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcRoundCorrection;

/**
 * What an approver needs for "Minta Revisi" at TH Progress level: the saved rounds of each
 * check stage to flag, and the audit of rounds QC already corrected (who/when/why and exactly
 * which values changed).
 */
class RoundAudit
{
    /** Columns that are bookkeeping, not data QC typed — never shown as a "change". */
    private const IGNORED = ['revision_no', 'finalize', 'user_id', 'average_weight'];

    /**
     * @return array{rounds: array<string, list<array<string, mixed>>>, history: list<array<string, mixed>>}
     */
    public static function forBatch(IpcBatch $batch): array
    {
        $rounds = [];

        $checks = [
            IpcBatch::STAGE_FILLING => $batch->fillingCheck,
            IpcBatch::STAGE_PACKING => $batch->packingCheck,
            IpcBatch::STAGE_FINISHED => $batch->finishedCheck,
        ];

        foreach ($checks as $stage => $check) {
            if (! $check) {
                continue;
            }

            $decisionKey = $stage === IpcBatch::STAGE_FINISHED ? 'disposition' : 'decision';

            $rounds[$stage] = $check->revisions()
                ->with('user:id,name')
                ->orderBy('revision_no')
                ->get()
                ->map(fn ($rev) => [
                    'no' => (int) $rev->revision_no,
                    'decision' => $rev->{$decisionKey},
                    'by' => $rev->user?->name,
                    'at' => $rev->created_at?->toIso8601String(),
                ])
                ->all();
        }

        $history = $batch->roundCorrections()
            ->where('status', IpcRoundCorrection::STATUS_DONE)
            ->with(['requester:id,name', 'corrector:id,name'])
            ->orderByDesc('corrected_at')
            ->get()
            ->map(fn (IpcRoundCorrection $c) => [
                'id' => $c->id,
                'stage' => $c->stage,
                'label' => (IpcApproval::STAGE_LABELS[$c->stage] ?? $c->stage).($c->revision_no ? ' TH #'.$c->revision_no : ''),
                'requested_by' => $c->requester?->name,
                'request_note' => $c->request_note,
                'corrected_by' => $c->corrector?->name,
                'corrected_at' => $c->corrected_at?->toIso8601String(),
                'reason' => $c->correction_reason,
                'changes' => self::changes($c),
            ])
            ->all();

        return ['rounds' => $rounds, 'history' => $history];
    }

    /** @return list<array{field: string, from: string, to: string}> */
    private static function changes(IpcRoundCorrection $c): array
    {
        $before = $c->before_data ?? [];
        $after = $c->after_data ?? [];
        $changes = [];

        if ($c->revision_no === null) {
            // Startup: whole-form snapshots — just say what group changed, not every column.
            foreach (['startup_check', 'batch', 'inspection', 'inspection_items', 'inspection_samples', 'inspection_tests'] as $part) {
                if (($before[$part] ?? null) != ($after[$part] ?? null)) {
                    $changes[] = ['field' => $part, 'from' => 'diubah', 'to' => 'diubah'];
                }
            }

            return $changes;
        }

        foreach (($after['revision'] ?? []) as $field => $to) {
            $from = $before['revision'][$field] ?? null;
            if (in_array($field, self::IGNORED, true) || (string) $from === (string) $to) {
                continue;
            }
            $changes[] = ['field' => $field, 'from' => self::show($from), 'to' => self::show($to)];
        }

        $key = fn (array $row) => (string) ($row['parameter_key'] ?? $row['sample_no'] ?? '');
        $beforeSamples = collect($before['samples'] ?? [])->keyBy($key);
        $afterSamples = collect($after['samples'] ?? [])->keyBy($key);

        foreach ($beforeSamples->keys()->merge($afterSamples->keys())->unique() as $sampleKey) {
            $from = $beforeSamples->get($sampleKey);
            $to = $afterSamples->get($sampleKey);
            $fromText = $from ? self::sampleText($from) : '—';
            $toText = $to ? self::sampleText($to) : '—';
            if ($fromText !== $toText) {
                $changes[] = ['field' => 'sample '.$sampleKey, 'from' => $fromText, 'to' => $toText];
            }
        }

        foreach (collect($before['photos'] ?? [])->keys()->merge(array_keys($after['photos'] ?? []))->unique() as $field) {
            $from = $before['photos'][$field] ?? null;
            $to = $after['photos'][$field] ?? null;
            if ($from !== $to) {
                $changes[] = ['field' => 'foto '.$field, 'from' => $from ? 'foto lama' : '—', 'to' => $to ? 'foto baru' : '—'];
            }
        }

        return $changes;
    }

    private static function sampleText(array $row): string
    {
        $parts = collect($row)->except(['parameter_key', 'sample_no'])->filter(fn ($v) => $v !== null && $v !== '');

        return $parts->isEmpty() ? '—' : $parts->map(fn ($v, $k) => $k.'='.$v)->implode(' ');
    }

    private static function show(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
