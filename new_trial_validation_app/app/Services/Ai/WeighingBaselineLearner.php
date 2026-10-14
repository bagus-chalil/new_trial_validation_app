<?php

namespace App\Services\Ai;

use App\Models\AiDataFlag;
use App\Models\AiLesson;
use Illuminate\Support\Facades\DB;

/**
 * Learns the normal weighing range per product + section, with no human in
 * the loop. Safety comes from rules: flagged/test/deleted trials are never
 * learned from, a baseline needs a minimum number of trials, the range uses
 * median/MAD (one bad value cannot move it), and a lesson the data no longer
 * supports is revoked automatically on the next run.
 */
class WeighingBaselineLearner
{
    public function __construct(
        private readonly LessonStore $store,
        private readonly int $minTrials = 5,
        private readonly float $sigmas = 3.0,
    ) {}

    /**
     * @return array{created:int, updated:int, unchanged:int, revoked:int, insufficient:int, excluded_trials:int}
     */
    public function learn(): array
    {
        [$groups, $excludedTrials] = $this->collect();

        $items = [];
        $insufficient = 0;
        foreach ($groups as $key => $perTrial) {
            if (count($perTrial) < $this->minTrials) {
                $insufficient++;

                continue;
            }
            $items[$key] = $this->lesson($key, $perTrial);
        }

        return $this->store->sync(AiLesson::KIND_WEIGHING_BASELINE, $items)
            + ['insufficient' => $insufficient, 'excluded_trials' => $excludedTrials];
    }

    /**
     * @return array{0: array<string, array<int, list<float>>>, 1: int} [product|section => [trial_id => values]], excluded count
     */
    private function collect(): array
    {
        $excluded = array_flip(
            AiDataFlag::query()->where('flag', AiDataFlag::SUSPECTED_TEST_TRIAL)->pluck('trial_id')->filter()->all()
        );

        $rows = DB::table('trials_weighing as w')
            ->join('trials_header as h', 'h.id', '=', 'w.trial_id')
            ->whereNull('h.deleted_at')
            ->where('w.is_skipped', 0)
            ->whereNotNull('w.weight_value')
            ->select('w.trial_id', 'w.section', 'w.weight_value', 'h.product_name')
            ->cursor();

        $groups = [];
        $seenExcluded = [];
        foreach ($rows as $row) {
            $product = trim((string) $row->product_name);
            if ($product === '' || ! is_numeric($row->weight_value) || (float) $row->weight_value <= 0) {
                continue;
            }
            if (isset($excluded[$row->trial_id])) {
                $seenExcluded[$row->trial_id] = true;

                continue;
            }
            $groups[$product.'|'.$row->section][$row->trial_id][] = (float) $row->weight_value;
        }

        return [$groups, count($seenExcluded)];
    }

    /**
     * @param  array<int, list<float>>  $perTrial
     * @return array{payload: array<string, mixed>, sample_size: int, confidence: string}
     */
    private function lesson(string $key, array $perTrial): array
    {
        $values = array_merge(...array_values($perTrial));
        $median = RobustStats::median($values);
        $mad = RobustStats::mad($values, $median);
        $spread = $this->sigmas * RobustStats::MAD_SCALE * $mad;
        $nTrials = count($perTrial);

        return [
            'payload' => [
                'product' => explode('|', $key)[0],
                'section' => explode('|', $key, 2)[1],
                'median' => round($median, 4),
                'mad' => round($mad, 4),
                'low' => round($median - $spread, 4),
                'high' => round($median + $spread, 4),
                'n_trials' => $nTrials,
            ],
            'sample_size' => count($values),
            'confidence' => $this->confidence($nTrials, $median, $mad),
        ];
    }

    /** Confidence from sample size and how tight the data is (relative MAD). */
    private function confidence(int $nTrials, float $median, float $mad): string
    {
        $relative = $median > 0 ? $mad / $median : 1.0;

        if ($nTrials >= 25 && $relative <= 0.05) {
            return 'tinggi';
        }
        if ($nTrials >= 10 && $relative <= 0.15) {
            return 'sedang';
        }

        return 'rendah';
    }
}
