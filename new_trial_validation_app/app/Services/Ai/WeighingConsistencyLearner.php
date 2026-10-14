<?php

namespace App\Services\Ai;

use App\Models\AiDataFlag;
use App\Models\AiLesson;
use Illuminate\Support\Facades\DB;

/**
 * Learns how consistent the samples inside ONE trial normally are, per
 * product_type + section. The measure is the coefficient of variation
 * (std / mean), which is scale-free, so a 10 g tube and an 80 g tube are
 * comparable even though their absolute weights are not. Same safety rules as
 * the baseline learner (clean trials only, minimum trials, median/MAD,
 * auto-revoke); the learned `high_cv` is the one-sided "unusually scattered"
 * threshold a later detector can compare a new trial against.
 */
class WeighingConsistencyLearner
{
    /** Floor so a perfectly uniform history does not yield a zero-width threshold. */
    private const MIN_SPREAD = 0.005;

    public function __construct(
        private readonly LessonStore $store,
        private readonly int $minTrials = 5,
        private readonly int $minSamplesPerTrial = 3,
        private readonly float $sigmas = 3.0,
    ) {}

    /**
     * @return array{created:int, updated:int, unchanged:int, revoked:int, insufficient:int}
     */
    public function learn(): array
    {
        $groups = $this->collect();

        $items = [];
        $insufficient = 0;
        foreach ($groups as $key => $cvs) {
            if (count($cvs) < $this->minTrials) {
                $insufficient++;

                continue;
            }
            $items[$key] = $this->lesson($key, $cvs);
        }

        return $this->store->sync(AiLesson::KIND_WEIGHING_CONSISTENCY, $items) + ['insufficient' => $insufficient];
    }

    /**
     * @return array<string, list<float>> product_type|section => per-trial CV values
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
            ->select('w.trial_id', 'w.section', 'w.weight_value', 'h.product_type')
            ->cursor();

        $perTrial = [];
        foreach ($rows as $row) {
            $type = trim((string) $row->product_type);
            if ($type === '' || isset($excluded[$row->trial_id]) || ! is_numeric($row->weight_value) || (float) $row->weight_value <= 0) {
                continue;
            }
            $perTrial[$type.'|'.$row->section][$row->trial_id][] = (float) $row->weight_value;
        }

        $groups = [];
        foreach ($perTrial as $key => $trials) {
            foreach ($trials as $values) {
                if (count($values) < $this->minSamplesPerTrial) {
                    continue;
                }
                $mean = array_sum($values) / count($values);
                $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / (count($values) - 1);
                $groups[$key][] = sqrt($variance) / $mean;
            }
        }

        return $groups;
    }

    /**
     * @param  list<float>  $cvs
     * @return array{payload: array<string, mixed>, sample_size: int, confidence: string}
     */
    private function lesson(string $key, array $cvs): array
    {
        $median = RobustStats::median($cvs);
        $mad = RobustStats::mad($cvs, $median);
        $spread = max(self::MIN_SPREAD, $this->sigmas * RobustStats::MAD_SCALE * $mad);
        $n = count($cvs);

        return [
            'payload' => [
                'product_type' => explode('|', $key)[0],
                'section' => explode('|', $key, 2)[1],
                'median_cv' => round($median, 5),
                'mad_cv' => round($mad, 5),
                'high_cv' => round($median + $spread, 5),
                'n_trials' => $n,
            ],
            'sample_size' => $n,
            'confidence' => $n >= 30 ? 'tinggi' : ($n >= 12 ? 'sedang' : 'rendah'),
        ];
    }
}
