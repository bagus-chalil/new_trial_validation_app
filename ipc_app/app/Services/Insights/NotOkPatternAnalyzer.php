<?php

namespace App\Services\Insights;

use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\StartupInspectionItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Finds checklist items that keep coming back as NOT OK ("pola NOT OK berulang").
 *
 * Read-only and rule-based: no model, no new table, nothing is written. Advisory only — it points
 * QC at a repeating finding, it never changes a decision. A "finding" is one checklist item that
 * was answered with a negative value on a batch (Not Conform, Not Available, Not OK, ...), counted
 * once per batch no matter how many TH Progress rounds repeated it.
 *
 * Two views over the same data:
 *  - forBatch(): the findings of ONE batch, each compared with the last WINDOW other batches of the
 *    same product and of the same line ("pernah NOT OK di 3 dari 10 batch terakhir produk ini").
 *  - overview(): the items that repeat most across all batches of the last N days, for the Dashboard.
 */
class NotOkPatternAnalyzer
{
    /** How many earlier batches of the same product / line a batch is compared with. */
    public const WINDOW = 10;

    /** An item counts as a repeating pattern once it was NOT OK on at least this many of those batches. */
    public const MIN_HITS = 2;

    private const OVERVIEW_CACHE_SECONDS = 600;

    /** Negative answers per checklist vocabulary. N/A and "Partial OK" are deliberately not findings. */
    private const NEGATIVE_STARTUP = [
        StartupCheck::STATUS_NOT_AVAILABLE,
        StartupCheck::STATUS_NOT_CONFORM,
        StartupCheck::STATUS_NOT_MATCH_WITH_BOM,
        StartupCheck::STATUS_BULK_NOT_YET_RELEASE,
        StartupCheck::STATUS_NOT_YET_COMPLETE,
    ];

    /**
     * Available / Not Available items that say "this does not apply here", not "this is defective".
     * On the real data (756 finished Startup Checks, 2026-11) Machine Roller / Sheetmask is "Not Available"
     * on 95% of batches, Machine Load Cell 91%, Machine Vision System 61%, Machine Weigher Check 48% and
     * Sample Challenge Test 39%: a line simply does not have every machine. Flagging them would bury every
     * real finding, so they are left out. Product Standard (0.4%) and WI / IM Match (1%) stay: those are rare
     * and mean a real gap. Machine Balance (2.5%) follows the other machines for consistency; QC may revisit.
     */
    private const NOT_APPLICABLE_STARTUP = [
        'sample_challenge_test_status',
        'machine_vision_status',
        'machine_weigher_status',
        'machine_roller_status',
        'machine_load_cell_status',
        'machine_balance_status',
    ];

    private const BAD_DECISIONS = [FillingCheck::DECISION_HOLD, FillingCheck::DECISION_REJECT];

    private const TIER_LABELS = ['primary' => 'Primary', 'secondary' => 'Secondary', 'tersier' => 'Tersier'];

    /**
     * Every item the analyzer can flag: key => label. The key's prefix (before the first dot) is
     * the check it belongs to, which is how "how many batches were even evaluated" is counted.
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        $catalog = [];

        foreach (self::startupFields() as $column => $label) {
            $catalog["startup.{$column}"] = "Startup · {$label}";
        }

        foreach (StartupInspectionItem::PARAMETER_KEYS as $key) {
            $catalog["inspection.{$key}"] = 'Start Inspection · '.self::humanize($key);
        }

        $catalog['filling.sample_bulk_odor_status'] = 'Filling · Bulk Odor';
        $catalog['filling.sample_leakage_test_status'] = 'Filling · Leakage Test';
        $catalog['filling.decision'] = 'Filling · Keputusan Hold / Reject';

        foreach (PackingCheck::checklistGroups() as $group) {
            $tier = self::TIER_LABELS[$group['key']] ?? self::humanize($group['key']);
            foreach ($group['fields'] as $column => $label) {
                $catalog["packing.{$column}"] = "Packing · {$tier} {$label}";
            }
        }
        $catalog['packing.decision'] = 'Packing · Keputusan Hold / Reject';

        $catalog['finished.disposition'] = 'Finished Good · Disposisi Rejected / Accepted With Remarks';

        return $catalog;
    }

    /**
     * Negative findings (and which checks were actually evaluated) for each of the given batches.
     *
     * @param  list<int>  $batchIds
     * @return array<int, array{findings: array<string, true>, evaluated: array<string, true>}>
     */
    public function findingsFor(array $batchIds): array
    {
        $out = [];
        foreach ($batchIds as $id) {
            $out[$id] = ['findings' => [], 'evaluated' => []];
        }
        if ($batchIds === []) {
            return $out;
        }

        $this->collectStartup($batchIds, $out);
        $this->collectInspection($batchIds, $out);
        $this->collectRounds($batchIds, $out, 'filling', 'filling_check_revisions', 'filling_checks', 'filling_check_id', ['sample_bulk_odor_status', 'sample_leakage_test_status'], StartupCheck::STATUS_NOT_CONFORM);
        $this->collectRounds($batchIds, $out, 'packing', 'packing_check_revisions', 'packing_checks', 'packing_check_id', $this->packingColumns(), PackingCheck::STATUS_NOT_CONFORM);
        $this->collectFinished($batchIds, $out);

        return $out;
    }

    /**
     * Startup Check columns the analyzer looks at: column => label, without the "does not apply" items.
     *
     * @return array<string, string>
     */
    private static function startupFields(): array
    {
        $fields = StartupCheck::PRODUCT_CHECK_FIELDS;
        foreach (StartupCheck::checklistGroups() as $group) {
            $fields += $group['fields'];
        }

        return array_diff_key($fields, array_flip(self::NOT_APPLICABLE_STARTUP));
    }

    /** @return list<string> */
    private function packingColumns(): array
    {
        $fields = [];
        foreach (PackingCheck::checklistGroups() as $group) {
            $fields[] = $group['fields'];
        }

        return array_keys(array_merge(...$fields));
    }

    /** Startup Check: one live row per batch. */
    private function collectStartup(array $batchIds, array &$out): void
    {
        $columns = array_keys(self::startupFields());

        foreach (DB::table('startup_checks')->whereIn('ipc_batch_id', $batchIds)->whereNotNull('completed_at')->get() as $row) {
            $out[$row->ipc_batch_id]['evaluated']['startup'] = true;
            foreach ($columns as $column) {
                if (in_array($row->{$column} ?? null, self::NEGATIVE_STARTUP, true)) {
                    $out[$row->ipc_batch_id]['findings']["startup.{$column}"] = true;
                }
            }
        }
    }

    /** Start Inspection: only a plain "Not OK" counts (Partial OK is too common to be a signal). */
    private function collectInspection(array $batchIds, array &$out): void
    {
        $items = DB::table('startup_inspection_items')
            ->join('startup_inspections', 'startup_inspections.id', '=', 'startup_inspection_items.startup_inspection_id')
            ->whereIn('startup_inspections.ipc_batch_id', $batchIds)
            ->whereNotNull('startup_inspections.completed_at')
            ->get(['startup_inspections.ipc_batch_id as batch_id', 'startup_inspection_items.parameter_key', 'startup_inspection_items.status']);

        foreach ($items as $item) {
            $out[$item->batch_id]['evaluated']['inspection'] = true;
            if ($item->status === StartupInspectionItem::STATUS_NOT_OK) {
                $out[$item->batch_id]['findings']["inspection.{$item->parameter_key}"] = true;
            }
        }
    }

    /**
     * Filling and Packing are repeated TH Progress rounds; every round is kept as a revision (the
     * live row is blanked after a draft save), so the revisions are the source of truth.
     *
     * @param  list<string>  $columns
     */
    private function collectRounds(array $batchIds, array &$out, string $prefix, string $revisionTable, string $parentTable, string $parentKey, array $columns, string $negative): void
    {
        $select = array_map(fn (string $c) => "{$revisionTable}.{$c}", [...$columns, 'decision']);
        $rows = DB::table($revisionTable)
            ->join($parentTable, "{$parentTable}.id", '=', "{$revisionTable}.{$parentKey}")
            ->whereIn("{$parentTable}.ipc_batch_id", $batchIds)
            ->get([...$select, "{$parentTable}.ipc_batch_id as batch_id"]);

        foreach ($rows as $row) {
            $out[$row->batch_id]['evaluated'][$prefix] = true;
            foreach ($columns as $column) {
                if ($row->{$column} === $negative) {
                    $out[$row->batch_id]['findings']["{$prefix}.{$column}"] = true;
                }
            }
            if (in_array($row->decision, self::BAD_DECISIONS, true)) {
                $out[$row->batch_id]['findings']["{$prefix}.decision"] = true;
            }
        }
    }

    private function collectFinished(array $batchIds, array &$out): void
    {
        $bad = [FinishedCheck::DISPOSITION_REJECTED, FinishedCheck::DISPOSITION_ACCEPTED_WITH_REMARKS];

        foreach (DB::table('finished_checks')->whereIn('ipc_batch_id', $batchIds)->whereNotNull('completed_at')->get(['ipc_batch_id', 'disposition']) as $row) {
            $out[$row->ipc_batch_id]['evaluated']['finished'] = true;
            if (in_array($row->disposition, $bad, true)) {
                $out[$row->ipc_batch_id]['findings']['finished.disposition'] = true;
            }
        }
    }

    /**
     * Findings of one batch, each compared with the last WINDOW other batches of the same product
     * and of the same line. Sorted: real patterns first, then by how often they repeated.
     *
     * @return list<array{key: string, label: string, stage: string, repeating: bool, product: ?array{hits: int, of: int}, line: ?array{hits: int, of: int}}>
     */
    public function forBatch(IpcBatch $batch): array
    {
        $current = array_keys($this->findingsFor([$batch->id])[$batch->id]['findings']);
        if ($current === []) {
            return [];
        }

        $catalog = self::catalog();
        $scopes = [];
        foreach (['product' => 'master_product_id', 'line' => 'master_line_id'] as $scope => $column) {
            if ($batch->{$column} === null) {
                continue;
            }
            $ids = IpcBatch::query()
                ->where($column, $batch->{$column})
                ->where('id', '!=', $batch->id)
                ->latest('id')
                ->limit(self::WINDOW)
                ->pluck('id')
                ->all();
            $scopes[$scope] = ['ids' => $ids, 'data' => $this->findingsFor($ids)];
        }

        $rows = [];
        foreach ($current as $key) {
            $row = ['key' => $key, 'label' => $catalog[$key] ?? $key, 'stage' => strtok($key, '.'), 'repeating' => false, 'product' => null, 'line' => null];
            foreach ($scopes as $scope => $info) {
                $hits = 0;
                $of = 0;
                foreach ($info['data'] as $data) {
                    $of += isset($data['evaluated'][$row['stage']]) ? 1 : 0;
                    $hits += isset($data['findings'][$key]) ? 1 : 0;
                }
                if ($hits > 0) {
                    $row[$scope] = ['hits' => $hits, 'of' => $of];
                    $row['repeating'] = $row['repeating'] || $hits >= self::MIN_HITS;
                }
            }
            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => [$b['repeating'], self::maxHits($b), $a['label']] <=> [$a['repeating'], self::maxHits($a), $b['label']]);

        return $rows;
    }

    /**
     * Items that repeat across the most batches in the last $days days (Dashboard card).
     *
     * @return list<array{key: string, label: string, batches: int, of: int, line: ?array{name: string, count: int}, product: ?array{name: string, count: int}}>
     */
    public function overview(int $days = 30, int $limit = 5): array
    {
        return Cache::remember("ipc-notok-overview:{$days}:{$limit}", self::OVERVIEW_CACHE_SECONDS, function () use ($days, $limit) {
            $batches = IpcBatch::query()
                ->where('created_at', '>=', now()->subDays($days))
                ->latest('id')
                ->limit(500)
                ->get(['id', 'master_product_id', 'master_line_id']);

            $data = $this->findingsFor($batches->pluck('id')->all());
            $catalog = self::catalog();

            $perKey = [];
            foreach ($batches as $batch) {
                foreach (array_keys($data[$batch->id]['findings']) as $key) {
                    $perKey[$key]['line'][$batch->master_line_id] = ($perKey[$key]['line'][$batch->master_line_id] ?? 0) + 1;
                    $perKey[$key]['product'][$batch->master_product_id] = ($perKey[$key]['product'][$batch->master_product_id] ?? 0) + 1;
                    $perKey[$key]['batches'] = ($perKey[$key]['batches'] ?? 0) + 1;
                }
            }

            $perKey = array_filter($perKey, fn (array $info) => $info['batches'] >= self::MIN_HITS);
            uasort($perKey, fn (array $a, array $b) => $b['batches'] <=> $a['batches']);
            $perKey = array_slice($perKey, 0, $limit, true);

            $lineNames = MasterLine::withTrashed()->whereIn('id', collect($perKey)->flatMap(fn ($i) => array_keys($i['line']))->filter()->unique())->get()->keyBy('id');
            $productNames = DB::table('master_products')->whereIn('id', collect($perKey)->flatMap(fn ($i) => array_keys($i['product']))->filter()->unique())->pluck('product_name', 'id');

            $rows = [];
            foreach ($perKey as $key => $info) {
                $stage = strtok($key, '.');
                $of = $batches->filter(fn ($b) => isset($data[$b->id]['evaluated'][$stage]))->count();

                arsort($info['line']);
                arsort($info['product']);
                $lineId = array_key_first(array_filter($info['line'], fn ($c, $id) => $id !== '' && $id !== null, ARRAY_FILTER_USE_BOTH));
                $productId = array_key_first($info['product']);

                $rows[] = [
                    'key' => $key,
                    'label' => $catalog[$key] ?? $key,
                    'batches' => $info['batches'],
                    'of' => $of,
                    'line' => $lineId !== null && $lineNames->has($lineId)
                        ? ['name' => $lineNames[$lineId]->name, 'count' => $info['line'][$lineId]]
                        : null,
                    'product' => $productId !== null && isset($productNames[$productId])
                        ? ['name' => $productNames[$productId], 'count' => $info['product'][$productId]]
                        : null,
                ];
            }

            return $rows;
        });
    }

    private static function maxHits(array $row): int
    {
        return max($row['product']['hits'] ?? 0, $row['line']['hits'] ?? 0);
    }

    private static function humanize(string $key): string
    {
        return implode(' ', array_map('ucfirst', explode('_', $key)));
    }
}
