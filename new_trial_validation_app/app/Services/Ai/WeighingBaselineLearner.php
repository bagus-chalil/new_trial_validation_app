<?php

namespace App\Services\Ai;

use App\Models\AiDataFlag;
use App\Models\AiLesson;
use Illuminate\Support\Carbon;
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
    /** MAD -> sigma equivalent for normal data. */
    private const MAD_SCALE = 1.4826;

    public function __construct(
        private readonly int $minTrials = 5,
        private readonly float $sigmas = 3.0,
    ) {}

    /**
     * @return array{created:int, updated:int, unchanged:int, revoked:int, insufficient:int, excluded_trials:int}
     */
    public function learn(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'revoked' => 0, 'insufficient' => 0, 'excluded_trials' => 0];

        $excluded = AiDataFlag::query()
            ->where('flag', AiDataFlag::SUSPECTED_TEST_TRIAL)
            ->pluck('trial_id')
            ->filter()
            ->all();
        $excluded = array_flip($excluded);

        // [product|section] => [trial_id => list<float>]
        $groups = [];
        $rows = DB::table('trials_weighing as w')
            ->join('trials_header as h', 'h.id', '=', 'w.trial_id')
            ->whereNull('h.deleted_at')
            ->where('w.is_skipped', 0)
            ->whereNotNull('w.weight_value')
            ->select('w.trial_id', 'w.section', 'w.weight_value', 'h.product_name')
            ->cursor();

        $seenExcluded = [];
        foreach ($rows as $row) {
            if (! is_numeric($row->weight_value) || (float) $row->weight_value <= 0) {
                continue;
            }
            if (isset($excluded[$row->trial_id])) {
                $seenExcluded[$row->trial_id] = true;

                continue;
            }
            $product = trim((string) $row->product_name);
            if ($product === '') {
                continue;
            }
            $groups[$product.'|'.$row->section][$row->trial_id][] = (float) $row->weight_value;
        }
        $stats['excluded_trials'] = count($seenExcluded);

        $now = Carbon::now();
        $seenKeys = [];

        foreach ($groups as $key => $perTrial) {
            if (count($perTrial) < $this->minTrials) {
                $stats['insufficient']++;

                continue;
            }

            $values = array_merge(...array_values($perTrial));
            $median = $this->median($values);
            $mad = $this->median(array_map(fn ($v) => abs($v - $median), $values));
            $spread = $this->sigmas * self::MAD_SCALE * $mad;

            $payload = [
                'product' => explode('|', $key)[0],
                'section' => explode('|', $key, 2)[1],
                'median' => round($median, 4),
                'mad' => round($mad, 4),
                'low' => round($median - $spread, 4),
                'high' => round($median + $spread, 4),
                'n_trials' => count($perTrial),
            ];
            $nTrials = count($perTrial);
            $confidence = $this->confidence($nTrials, $median, $mad);
            $seenKeys[$key] = true;

            $lesson = AiLesson::query()
                ->where('kind', AiLesson::KIND_WEIGHING_BASELINE)
                ->where('scope_key', $key)
                ->first();

            if ($lesson === null) {
                AiLesson::create([
                    'kind' => AiLesson::KIND_WEIGHING_BASELINE,
                    'scope_key' => $key,
                    'payload' => $payload,
                    'sample_size' => count($values),
                    'confidence' => $confidence,
                    'status' => AiLesson::STATUS_ACTIVE,
                    'version' => 1,
                    'learned_at' => $now,
                ]);
                $stats['created']++;

                continue;
            }

            // Loose compare: JSON turns 10.0 into 10, which must not count as a change.
            $changed = $lesson->payload != $payload
                || $lesson->confidence !== $confidence
                || $lesson->status !== AiLesson::STATUS_ACTIVE;
            if (! $changed) {
                $stats['unchanged']++;

                continue;
            }

            $lesson->fill([
                'payload' => $payload,
                'sample_size' => count($values),
                'confidence' => $confidence,
                'status' => AiLesson::STATUS_ACTIVE,
                'version' => $lesson->version + 1,
                'learned_at' => $now,
                'revoked_at' => null,
            ])->save();
            $stats['updated']++;
        }

        // Auto-expire: anything active that this run no longer supports.
        AiLesson::query()
            ->where('kind', AiLesson::KIND_WEIGHING_BASELINE)
            ->where('status', AiLesson::STATUS_ACTIVE)
            ->get()
            ->each(function (AiLesson $lesson) use ($seenKeys, $now, &$stats) {
                if (isset($seenKeys[$lesson->scope_key])) {
                    return;
                }
                $lesson->fill(['status' => AiLesson::STATUS_REVOKED, 'revoked_at' => $now])->save();
                $stats['revoked']++;
            });

        return $stats;
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

    /** @param  list<float>  $values */
    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
