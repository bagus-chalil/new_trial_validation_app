<?php

namespace App\Services\Ai;

use App\Models\AiDataFlag;
use App\Models\AiLesson;
use Illuminate\Support\Facades\DB;

/**
 * Learns how long workflow steps normally take, so a later detector can say
 * "this has been waiting unusually long". Three metrics, all in hours:
 *
 *  - prepare|<product_type>  trial created -> FIRST submit-for-review
 *  - review|<department>     latest submit-for-review before it -> that department's review
 *  - approval|all            latest submit-for-review before it -> final approve/reject
 *
 * Submit times come from the activity log (TRIAL / SUBMIT_REVIEW), the only place
 * resubmissions after a revision are recorded. Durations are right-skewed, so the
 * stats are rank-based: `typical_hours` is the median, `slow_hours` the 90th
 * percentile (a sigma-style threshold is meaningless on such heavy tails). Same safety rules as the other learners:
 * clean trials only, a minimum sample, auto-revoke via LessonStore.
 */
class StepDurationLearner
{
    /** Anything under a minute is a double click or a data fix, not a real duration. */
    private const MIN_HOURS = 1 / 60;

    /** "Unusually slow" = slower than this share of past cases. Empirical, so heavy tails cannot inflate it. */
    private const SLOW_PERCENTILE = 90;

    public function __construct(
        private readonly LessonStore $store,
        private readonly int $minSamples = 5,
    ) {}

    /**
     * @return array{created:int, updated:int, unchanged:int, revoked:int, insufficient:int}
     */
    public function learn(): array
    {
        $groups = $this->collect();

        $items = [];
        $insufficient = 0;
        foreach ($groups as $key => $hours) {
            if (count($hours) < $this->minSamples) {
                $insufficient++;

                continue;
            }
            $items[$key] = $this->lesson($key, $hours);
        }

        return $this->store->sync(AiLesson::KIND_STEP_DURATION, $items) + ['insufficient' => $insufficient];
    }

    /**
     * @return array<string, list<float>> metric|scope => durations in hours
     */
    private function collect(): array
    {
        $excluded = array_flip(
            AiDataFlag::query()->where('flag', AiDataFlag::SUSPECTED_TEST_TRIAL)->pluck('trial_id')->filter()->all()
        );

        /** @var array<int, list<int>> $submits trial_id => sorted unix timestamps */
        $submits = [];
        $logs = DB::table('activity_logs')
            ->where('module', 'TRIAL')->where('action', 'SUBMIT_REVIEW')
            ->orderBy('created_at')
            ->select('record_id', 'created_at')
            ->cursor();
        foreach ($logs as $log) {
            if (is_numeric($log->record_id) && ($ts = strtotime((string) $log->created_at)) !== false) {
                $submits[(int) $log->record_id][] = $ts;
            }
        }

        $trials = DB::table('trials_header')
            ->whereNull('deleted_at')
            ->select('id', 'product_type', 'created_at', 'final_decision', 'approved_at', 'rejected_at')
            ->get()
            ->filter(fn ($t) => ! isset($excluded[$t->id]) && isset($submits[$t->id]));

        $groups = [];
        $add = function (string $key, ?int $from, ?int $to) use (&$groups): void {
            if ($from === null || $to === null) {
                return;
            }
            $hours = ($to - $from) / 3600;
            if ($hours >= self::MIN_HOURS) {
                $groups[$key][] = $hours;
            }
        };

        foreach ($trials as $t) {
            $type = trim((string) $t->product_type);
            $created = $t->created_at ? (strtotime((string) $t->created_at) ?: null) : null;
            if ($type !== '') {
                $add('prepare|'.$type, $created, $submits[$t->id][0]);
            }

            $decidedAt = $t->approved_at ?? $t->rejected_at;
            $decided = $decidedAt ? (strtotime((string) $decidedAt) ?: null) : null;
            $add('approval|all', $this->latestBefore($submits[$t->id], $decided), $decided);
        }

        $reviews = DB::table('trials_review')
            ->whereNotNull('reviewed_at')
            ->where('status', 'Reviewed')
            ->select('trial_id', 'department', 'reviewed_at')
            ->cursor();
        foreach ($reviews as $r) {
            if (! isset($submits[$r->trial_id]) || isset($excluded[$r->trial_id]) || ! $trials->contains('id', $r->trial_id)) {
                continue;
            }
            $reviewed = strtotime((string) $r->reviewed_at) ?: null;
            $add('review|'.$r->department, $this->latestBefore($submits[$r->trial_id], $reviewed), $reviewed);
        }

        return $groups;
    }

    /** @param  list<int>  $sorted */
    private function latestBefore(array $sorted, ?int $at): ?int
    {
        if ($at === null) {
            return null;
        }
        $found = null;
        foreach ($sorted as $ts) {
            if ($ts <= $at) {
                $found = $ts;
            }
        }

        return $found;
    }

    /**
     * @param  list<float>  $hours
     * @return array{payload: array<string, mixed>, sample_size: int, confidence: string}
     */
    private function lesson(string $key, array $hours): array
    {
        $median = RobustStats::median($hours);
        [$metric, $scope] = explode('|', $key, 2);
        $n = count($hours);

        return [
            'payload' => [
                'metric' => $metric,
                'scope' => $scope,
                'typical_hours' => round($median, 2),
                'slow_hours' => round(RobustStats::percentile($hours, self::SLOW_PERCENTILE), 2),
                'n' => $n,
            ],
            'sample_size' => $n,
            'confidence' => $n >= 30 ? 'tinggi' : ($n >= 12 ? 'sedang' : 'rendah'),
        ];
    }
}
