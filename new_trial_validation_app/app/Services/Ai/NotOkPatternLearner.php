<?php

namespace App\Services\Ai;

use App\Models\AiDataFlag;
use App\Models\AiLesson;
use Illuminate\Support\Facades\DB;

/**
 * Learns how often each validation parameter comes out NOT OK, per
 * product_type + parameter. Only OK / NOT OK decisions count (N/A says nothing
 * about pass/fail), each trial counts once per parameter, flagged test trials
 * and soft-deleted trials are excluded, and a group needs a minimum number of
 * trials before a lesson exists. Free-text remarks are deliberately not stored.
 * `hotspot` marks parameters that fail often enough to deserve attention.
 */
class NotOkPatternLearner
{
    public function __construct(
        private readonly LessonStore $store,
        private readonly int $minTrials = 5,
        private readonly int $hotspotMinNotOk = 3,
        private readonly float $hotspotMinRate = 0.2,
    ) {}

    /**
     * @return array{created:int, updated:int, unchanged:int, revoked:int, insufficient:int}
     */
    public function learn(): array
    {
        $excluded = array_flip(
            AiDataFlag::query()->where('flag', AiDataFlag::SUSPECTED_TEST_TRIAL)->pluck('trial_id')->filter()->all()
        );

        $rows = DB::table('trials_results as r')
            ->join('trials_header as h', 'h.id', '=', 'r.trial_id')
            ->join('validation_parameters as p', 'p.id', '=', 'r.parameter_id')
            ->whereNull('h.deleted_at')
            ->whereIn('r.decision', ['OK', 'NOT OK'])
            ->select('r.trial_id', 'r.parameter_id', 'r.decision', 'h.product_type', 'p.parameter_name')
            ->cursor();

        $groups = [];
        foreach ($rows as $row) {
            $type = trim((string) $row->product_type);
            if ($type === '' || isset($excluded[$row->trial_id])) {
                continue;
            }
            $key = $type.'|'.$row->parameter_id;
            $groups[$key]['name'] = (string) $row->parameter_name;
            $groups[$key]['trials'][$row->trial_id] = ($groups[$key]['trials'][$row->trial_id] ?? false) || $row->decision === 'NOT OK';
        }

        $items = [];
        $insufficient = 0;
        foreach ($groups as $key => $group) {
            $n = count($group['trials']);
            if ($n < $this->minTrials) {
                $insufficient++;

                continue;
            }
            $notOk = count(array_filter($group['trials']));
            $rate = $notOk / $n;
            [$type, $parameterId] = explode('|', $key, 2);

            $items[$key] = [
                'payload' => [
                    'product_type' => $type,
                    'parameter_id' => (int) $parameterId,
                    'parameter_name' => $group['name'],
                    'n_trials' => $n,
                    'not_ok_trials' => $notOk,
                    'not_ok_rate' => round($rate, 4),
                    'hotspot' => $notOk >= $this->hotspotMinNotOk && $rate >= $this->hotspotMinRate,
                ],
                'sample_size' => $n,
                'confidence' => $n >= 30 ? 'tinggi' : ($n >= 12 ? 'sedang' : 'rendah'),
            ];
        }

        return $this->store->sync(AiLesson::KIND_NOT_OK_PATTERN, $items) + ['insufficient' => $insufficient];
    }
}
