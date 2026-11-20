<?php

namespace App\Services\Insights;

use App\Models\IpcBatch;
use App\Models\IpcLog;
use Illuminate\Support\Facades\DB;

/**
 * Plain-fact digest of one batch for the approver ("ringkasan batch"). Everything here is computed
 * by rules from data already in the database, so it is always available and always correct about
 * what was recorded. The optional LLM step (BatchSummaryAnalyzer, written by the Vision Service)
 * only rewords these points; it is never the source of a fact, and nothing here recommends approving or rejecting.
 */
class BatchSummaryBuilder
{
    private const MAX_FINDINGS = 8;

    private const MAX_REMARK_CHARS = 200;

    private const TRIVIAL_REMARKS = ['ok', 'oke', 'okay', '-', '--', 'n/a', 'na', 'baik', 'normal', 'sesuai', 'tidak ada', 'nihil', 'aman'];

    public function __construct(private readonly NotOkPatternAnalyzer $patterns) {}

    /**
     * @return array{
     *     points: list<string>,
     *     findings: list<array{label: string, repeating: bool, product: ?array{hits: int, of: int}, line: ?array{hits: int, of: int}}>
     * }
     */
    public function build(IpcBatch $batch): array
    {
        $batch->loadMissing(['masterProduct', 'masterLine', 'startupCheck', 'fillingCheck', 'packingCheck', 'finishedCheck']);

        $findings = $this->patterns->forBatch($batch);

        $points = array_values(array_filter([
            $this->identityLine($batch),
            $this->roundsLine($batch),
            $this->findingsLine($findings),
            ...$this->patternLines($findings),
            $this->decisionsLine($batch),
            $this->aiLine($batch),
            ...$this->remarkLines($batch),
        ]));

        return [
            'points' => $points,
            'findings' => array_map(fn (array $f) => [
                'label' => $f['label'],
                'repeating' => $f['repeating'],
                'product' => $f['product'],
                'line' => $f['line'],
            ], array_slice($findings, 0, self::MAX_FINDINGS)),
        ];
    }

    private function identityLine(IpcBatch $batch): string
    {
        $line = $batch->masterLine ? "{$batch->masterLine->name}" : 'line belum dipilih';

        return sprintf(
            'Batch %s, produk %s (FG %s), %s, alur %s.',
            $batch->no_batch,
            $batch->masterProduct?->product_name ?? '-',
            $batch->masterProduct?->fg_code ?? '-',
            $line,
            IpcBatch::FLOW_SHORT_LABELS[$batch->flow_type] ?? $batch->flow_type,
        );
    }

    private function roundsLine(IpcBatch $batch): ?string
    {
        $rounds = [];
        foreach (['Filling' => $batch->fillingCheck, 'Packing' => $batch->packingCheck, 'Finished Good' => $batch->finishedCheck] as $label => $check) {
            if ($check !== null) {
                $rounds[] = "{$label} {$check->save_count} ronde";
            }
        }

        return $rounds === [] ? null : 'TH Progress: '.implode(', ', $rounds).'.';
    }

    /** @param list<array<string, mixed>> $findings */
    private function findingsLine(array $findings): string
    {
        if ($findings === []) {
            return 'Tidak ada item NOT OK pada checklist Startup, Start Inspection, Filling, Packing, maupun disposisi Finished Good.';
        }

        $labels = array_map(fn (array $f) => $f['label'], array_slice($findings, 0, self::MAX_FINDINGS));
        $more = count($findings) - count($labels);

        return sprintf('%d item NOT OK pada batch ini: %s%s.', count($findings), implode('; ', $labels), $more > 0 ? "; dan {$more} lainnya" : '');
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<string>
     */
    private function patternLines(array $findings): array
    {
        $lines = [];
        foreach ($findings as $f) {
            if (! $f['repeating']) {
                continue;
            }
            $where = [];
            if ($f['product']) {
                $where[] = "{$f['product']['hits']} dari {$f['product']['of']} batch produk yang sama";
            }
            if ($f['line']) {
                $where[] = "{$f['line']['hits']} dari {$f['line']['of']} batch di line yang sama";
            }
            $lines[] = sprintf('Berulang: "%s" juga NOT OK di %s (dari %d batch terakhir masing-masing).', $f['label'], implode(' dan ', $where), NotOkPatternAnalyzer::WINDOW);
        }

        return $lines;
    }

    private function decisionsLine(IpcBatch $batch): ?string
    {
        $parts = [];

        foreach (['Filling' => 'fillingCheck', 'Packing' => 'packingCheck'] as $label => $relation) {
            $check = $batch->{$relation};
            if ($check?->completed_at && $check->decision) {
                $parts[] = "{$label}: {$check->decision}";
            }
        }
        if ($batch->finishedCheck?->completed_at && $batch->finishedCheck->disposition) {
            $parts[] = "Finished Good: {$batch->finishedCheck->disposition}";
        }

        return $parts === [] ? null : 'Keputusan QC: '.implode(', ', $parts).'.';
    }

    /** Verdict counts of the newest Startup AI read per field (advisory reads, not decisions). */
    private function aiLine(IpcBatch $batch): ?string
    {
        $latestIds = IpcLog::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', IpcBatch::STAGE_STARTUP)
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('field_type');

        $counts = IpcLog::query()->whereIn('id', $latestIds)->pluck('decision')->countBy();

        if ($counts->isEmpty()) {
            return null;
        }

        $parts = $counts->map(fn (int $n, string $decision) => "{$n} {$decision}")->values()->implode(', ');

        return "Bacaan AI foto Startup (saran saja): {$parts}.";
    }

    /** @return list<string> */
    private function remarkLines(IpcBatch $batch): array
    {
        $lines = [];
        $remarks = [
            'Startup' => $batch->startupCheck?->remarks,
            'Filling' => $batch->fillingCheck?->remarks,
            'Packing' => $batch->packingCheck?->remarks,
            'Finished Good' => $batch->finishedCheck?->remarks,
        ];

        foreach ($remarks as $label => $text) {
            $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));
            // "OK" / "-" style remarks carry no information, so they are not worth a line (or a prompt token).
            if ($text !== '' && ! in_array(mb_strtolower(rtrim($text, '. ')), self::TRIVIAL_REMARKS, true)) {
                $lines[] = sprintf('Catatan QC %s: "%s".', $label, mb_substr($text, 0, self::MAX_REMARK_CHARS));
            }
        }

        return $lines;
    }
}
