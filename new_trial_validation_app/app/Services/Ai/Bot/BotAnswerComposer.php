<?php

namespace App\Services\Ai\Bot;

use App\Services\Pdf\ExportFormat;

/**
 * Turns the plain data returned by TrialBotTools into a sentence in the
 * current app locale, using the lang/*\/ai.php templates. Pure: no database,
 * no policies — whatever it is handed has already been scoped by the tools.
 *
 * Result: `text` (multi-line answer), `trials` (trial ids/codes mentioned, so
 * the chat widget can link each to its Report Summary page) and `suggestions`
 * (example questions, only for unknown intents).
 *
 * @phpstan-type BotAnswer array{text:string, trials:array<int, array{id:int, trial_code:string}>, suggestions:array<int, string>}
 */
class BotAnswerComposer
{
    /**
     * @param  array<string, mixed>|null  $data  the tool result for the intent (null = nothing found)
     * @param  array<string, mixed>  $params  the recognizer params (used to word "not found" answers)
     * @return BotAnswer
     */
    public function compose(string $intent, ?array $data = null, array $params = []): array
    {
        return match ($intent) {
            IntentRecognizer::INTENT_MY_QUEUE => $this->myQueue($data ?? []),
            IntentRecognizer::INTENT_TRIALS_BY_STATUS => $this->trialsByStatus($data ?? []),
            IntentRecognizer::INTENT_TRIAL_STATUS => $this->trialStatus($data, $params),
            IntentRecognizer::INTENT_STUCK_TRIALS => $this->stuckTrials($data ?? []),
            IntentRecognizer::INTENT_NOT_OK_HOTSPOTS => $this->notOkHotspots($data ?? []),
            IntentRecognizer::INTENT_WEIGHING_NORM => $this->weighingNorm($data, $params),
            IntentRecognizer::INTENT_HELP => $this->help(),
            IntentRecognizer::INTENT_SUMMARY => $this->summary($data ?? []),
            IntentRecognizer::INTENT_MY_TRIALS => $this->myTrials($data ?? []),
            IntentRecognizer::INTENT_LATEST_TRIALS => $this->latestTrials($data ?? []),
            IntentRecognizer::INTENT_SEARCH_TRIALS => $this->searchTrials($data ?? [], $params),
            IntentRecognizer::INTENT_TRIAL_NOT_OK => $this->trialNotOk($data, $params),
            IntentRecognizer::INTENT_TRIAL_REVIEWS => $this->trialReviews($data, $params),
            IntentRecognizer::INTENT_STEP_DURATION => $this->stepDuration($data ?? []),
            IntentRecognizer::INTENT_HOW_TO => $this->howTo($params),
            default => $this->unknown(),
        };
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function myQueue(array $d): array
    {
        $lines = [];
        foreach (['draft', 'need_revision', 'in_progress', 'pending_reviews', 'pending_approvals'] as $key) {
            $count = (int) ($d[$key] ?? 0);
            if ($count > 0) {
                $lines[] = '- '.__('ai.answers.my_queue.'.$key, ['count' => $count]);
            }
        }

        if ($lines === []) {
            return $this->result(__('ai.answers.my_queue.empty'));
        }

        return $this->result(implode("\n", [__('ai.answers.my_queue.intro'), ...$lines]));
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function trialsByStatus(array $d): array
    {
        $filter = '';
        if (! empty($d['status'])) {
            $filter .= __('ai.answers.trials_by_status.filter_status', ['status' => ExportFormat::trialStatus($d['status'])]);
        }
        if (! empty($d['product_type'])) {
            $filter .= __('ai.answers.trials_by_status.filter_type', ['type' => $d['product_type']]);
        }

        $total = (int) ($d['total'] ?? 0);
        if ($total === 0) {
            return $this->result(__('ai.answers.trials_by_status.none', ['filter' => $filter]));
        }

        $trials = (array) ($d['trials'] ?? []);
        $lines = [__('ai.answers.trials_by_status.total', ['total' => $total, 'filter' => $filter])];

        if ($trials !== []) {
            $lines[] = __('ai.answers.trials_by_status.latest').(count($trials) < $total
                ? ' '.__('ai.answers.trials_by_status.more', ['shown' => count($trials)])
                : '');
            foreach ($trials as $t) {
                $lines[] = '- '.$t['trial_code'].' ('.$t['product_name'].') - '.ExportFormat::trialStatus($t['progress_status']);
            }
        }

        return $this->result(implode("\n", $lines), $trials);
    }

    /**
     * @param  array<string, mixed>|null  $d
     * @param  array<string, mixed>  $params
     * @return BotAnswer
     */
    private function trialStatus(?array $d, array $params): array
    {
        if ($d === null) {
            return $this->result(__('ai.answers.trial_status.not_found', ['code' => (string) ($params['trial_code'] ?? '')]));
        }

        $lines = [__('ai.answers.trial_status.found', [
            'code' => $d['trial_code'],
            'product' => $d['product_name'],
            'status' => ExportFormat::trialStatus($d['progress_status']),
            'step' => $d['current_step'] ?? '-',
        ])];

        if (! empty($d['pending_with'])) {
            $lines[] = __('ai.answers.trial_status.pending_with', ['who' => $d['pending_with']]);
        }
        if ((int) ($d['revision_no'] ?? 0) > 0) {
            $lines[] = __('ai.answers.trial_status.revision', ['no' => (int) $d['revision_no']]);
        }

        return $this->result(implode("\n", $lines), [$d]);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function stuckTrials(array $d): array
    {
        $days = (int) ($d['days'] ?? 14);
        $total = (int) ($d['total'] ?? 0);

        if ($total === 0) {
            return $this->result(__('ai.answers.stuck_trials.none', ['days' => $days]));
        }

        $trials = (array) ($d['trials'] ?? []);
        $lines = [__('ai.answers.stuck_trials.total', ['total' => $total, 'days' => $days])];
        foreach ($trials as $t) {
            $lines[] = '- '.__('ai.answers.stuck_trials.item', [
                'code' => $t['trial_code'],
                'product' => $t['product_name'],
                'status' => ExportFormat::trialStatus($t['progress_status']),
                'who' => $t['pending_with'] ?: __('ai.answers.stuck_trials.nobody'),
                'date' => ExportFormat::date($t['updated_at']),
            ]);
        }

        return $this->result(implode("\n", $lines), $trials);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function notOkHotspots(array $d): array
    {
        $parameters = (array) ($d['parameters'] ?? []);

        if ($parameters === []) {
            return $this->result(__('ai.answers.not_ok_hotspots.none'));
        }

        $lines = [__('ai.answers.not_ok_hotspots.intro')];
        foreach ($parameters as $p) {
            $lines[] = '- '.__('ai.answers.not_ok_hotspots.item', [
                'type' => $p['product_type'],
                'parameter' => $p['parameter_name'],
                'count' => $p['not_ok_trials'],
                'total' => $p['n_trials'],
                'rate' => round(((float) $p['not_ok_rate']) * 100),
            ]);
        }
        $lines[] = __('ai.answers.not_ok_hotspots.scope');

        return $this->result(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>|null  $d
     * @param  array<string, mixed>  $params
     * @return BotAnswer
     */
    private function weighingNorm(?array $d, array $params): array
    {
        if ($d === null) {
            $type = $params['product_type'] ?? null;
            $section = $params['section'] ?? null;

            return $type && $section
                ? $this->result(__('ai.answers.weighing_norm.none', ['type' => $type, 'section' => $section]))
                : $this->result(__('ai.answers.weighing_norm.need_type'));
        }

        return $this->result(__('ai.answers.weighing_norm.found', [
            'type' => $d['product_type'],
            'section' => $d['section'],
            'median' => round(((float) $d['median_cv']) * 100, 2),
            'high' => round(((float) $d['high_cv']) * 100, 2),
            'n' => $d['n_trials'],
            'confidence' => __('ai.confidence.'.($d['confidence'] ?? 'rendah')),
        ]));
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function summary(array $d): array
    {
        $total = (int) ($d['total'] ?? 0);
        if ($total === 0) {
            return $this->result(__('ai.answers.summary.none'));
        }

        $lines = [__('ai.answers.summary.intro', [
            'total' => $total,
            'filling' => (int) ($d['total_filling'] ?? 0),
            'mixing' => (int) ($d['total_mixing'] ?? 0),
        ])];
        foreach (['draft' => 'Draft', 'in_review' => 'In Review', 'ready' => 'Ready for Approval', 'approved' => 'Approved', 'need_revision' => 'Need Revision', 'rejected' => 'Rejected'] as $key => $status) {
            if ((int) ($d[$key] ?? 0) > 0) {
                $lines[] = '- '.ExportFormat::trialStatus($status).': '.(int) $d[$key];
            }
        }

        return $this->result(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function myTrials(array $d): array
    {
        $filter = $this->filterText($d);
        $total = (int) ($d['total'] ?? 0);
        if ($total === 0) {
            return $this->result(__('ai.answers.my_trials.none', ['filter' => $filter]));
        }

        return $this->listResult(__('ai.answers.my_trials.total', ['total' => $total, 'filter' => $filter]), (array) ($d['trials'] ?? []), $total);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function latestTrials(array $d): array
    {
        $filter = $this->filterText($d);
        $trials = (array) ($d['trials'] ?? []);
        if ($trials === []) {
            return $this->result(__('ai.answers.latest_trials.none', ['filter' => $filter]));
        }

        return $this->listResult(__('ai.answers.latest_trials.intro', ['count' => count($trials), 'filter' => $filter]), $trials, count($trials));
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, mixed>  $params
     * @return BotAnswer
     */
    private function searchTrials(array $d, array $params): array
    {
        $keyword = (string) ($d['keyword'] ?? $params['keyword'] ?? '');
        $total = (int) ($d['total'] ?? 0);
        if ($total === 0) {
            return $this->result(__('ai.answers.search_trials.none', ['keyword' => $keyword]));
        }

        return $this->listResult(__('ai.answers.search_trials.total', ['total' => $total, 'keyword' => $keyword]), (array) ($d['trials'] ?? []), $total);
    }

    /**
     * @param  array<string, mixed>|null  $d
     * @param  array<string, mixed>  $params
     * @return BotAnswer
     */
    private function trialNotOk(?array $d, array $params): array
    {
        if ($d === null) {
            return $this->result(__('ai.answers.trial_status.not_found', ['code' => (string) ($params['trial_code'] ?? '')]));
        }

        $trial = (array) $d['trial'];
        $notOk = (array) ($d['not_ok'] ?? []);
        if ($notOk === []) {
            $key = (int) ($d['checked'] ?? 0) > 0 ? 'none' : 'not_validated';

            return $this->result(__('ai.answers.trial_not_ok.'.$key, ['code' => $trial['trial_code']]), [$trial]);
        }

        $lines = [__('ai.answers.trial_not_ok.intro', ['code' => $trial['trial_code'], 'count' => count($notOk), 'total' => (int) ($d['checked'] ?? 0)])];
        foreach ($notOk as $p) {
            $lines[] = '- '.__('ai.answers.trial_not_ok.item', [
                'parameter' => $p['parameter_name'],
                'result' => $this->orDash($p['result_value'] ?? null),
                'remark' => $this->orDash($p['remark'] ?? null),
            ]);
        }

        return $this->result(implode("\n", $lines), [$trial]);
    }

    /**
     * @param  array<string, mixed>|null  $d
     * @param  array<string, mixed>  $params
     * @return BotAnswer
     */
    private function trialReviews(?array $d, array $params): array
    {
        if ($d === null) {
            return $this->result(__('ai.answers.trial_status.not_found', ['code' => (string) ($params['trial_code'] ?? '')]));
        }

        $trial = (array) $d['trial'];
        $reviews = (array) ($d['reviews'] ?? []);
        if ($reviews === []) {
            return $this->result(__('ai.answers.trial_reviews.none', ['code' => $trial['trial_code']]), [$trial]);
        }

        $lines = [__('ai.answers.trial_reviews.intro', ['code' => $trial['trial_code'], 'round' => (int) ($d['round'] ?? 1)])];
        foreach ($reviews as $r) {
            $lines[] = '- '.($r['status'] === 'Reviewed'
                ? __('ai.answers.trial_reviews.done', [
                    'dept' => $r['department'],
                    'who' => $this->orDash($r['reviewer'] ?? null),
                    'date' => ExportFormat::date($r['reviewed_at']),
                ])
                : __('ai.answers.trial_reviews.pending', [
                    'dept' => $r['department'],
                    'who' => $this->orDash($r['reviewer'] ?? null),
                ]));
        }

        return $this->result(implode("\n", $lines), [$trial]);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return BotAnswer
     */
    private function stepDuration(array $d): array
    {
        $durations = (array) ($d['durations'] ?? []);
        if ($durations === []) {
            return $this->result(__('ai.answers.step_duration.none'));
        }

        $lines = [__('ai.answers.step_duration.intro')];
        foreach ($durations as $x) {
            $lines[] = '- '.__('ai.answers.step_duration.item', [
                'stage' => __('ai.answers.step_duration.stages.'.$x['metric']),
                'scope' => $x['metric'] === 'approval' ? '' : ' '.$x['scope'],
                'typical' => $this->duration((float) $x['typical_hours']),
                'slow' => $this->duration((float) $x['slow_hours']),
                'n' => (int) $x['n'],
            ]);
        }
        $lines[] = __('ai.answers.step_duration.scope');

        return $this->result(implode("\n", $lines));
    }

    /**
     * Static FAQ: one topic, or the list of topics when none was recognized.
     *
     * @param  array<string, mixed>  $params
     * @return BotAnswer
     */
    private function howTo(array $params): array
    {
        $topic = $params['topic'] ?? null;

        if (is_string($topic) && $topic !== '') {
            return $this->result(__('ai.answers.how_to.topics.'.$topic));
        }

        return $this->withExamples(__('ai.answers.how_to.choose'), array_values((array) __('ai.how_to_examples')));
    }

    private function duration(float $hours): string
    {
        return $hours < 48
            ? __('ai.answers.step_duration.hours', ['n' => round($hours, 1)])
            : __('ai.answers.step_duration.days', ['n' => round($hours / 24, 1)]);
    }

    private function orDash(mixed $value): string
    {
        $text = trim((string) $value);

        return $text === '' ? '-' : $text;
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function filterText(array $d): string
    {
        $filter = '';
        if (! empty($d['status'])) {
            $filter .= __('ai.answers.trials_by_status.filter_status', ['status' => ExportFormat::trialStatus($d['status'])]);
        }
        if (! empty($d['product_type'])) {
            $filter .= __('ai.answers.trials_by_status.filter_type', ['type' => $d['product_type']]);
        }

        return $filter;
    }

    /**
     * @param  array<int, array<string, mixed>>  $trials
     * @return BotAnswer
     */
    private function listResult(string $header, array $trials, int $total): array
    {
        $lines = [$header];
        if (count($trials) < $total) {
            $lines[] = __('ai.answers.trials_by_status.more', ['shown' => count($trials)]);
        }
        foreach ($trials as $t) {
            $lines[] = '- '.$t['trial_code'].' ('.$t['product_name'].') - '.ExportFormat::trialStatus($t['progress_status']);
        }

        return $this->result(implode("\n", $lines), $trials);
    }

    /** @return BotAnswer */
    private function unknown(): array
    {
        return $this->withExamples(__('ai.answers.unknown.text'));
    }

    /** @return BotAnswer */
    private function help(): array
    {
        return $this->withExamples(__('ai.answers.help.text'));
    }

    /**
     * @param  array<int, string>|null  $suggestions  defaults to one example per suggestable intent
     * @return BotAnswer
     */
    private function withExamples(string $text, ?array $suggestions = null): array
    {
        $suggestions ??= array_map(
            fn (string $intent) => __('ai.examples.'.$intent),
            (new IntentRecognizer)->suggestableIntents(),
        );

        return [
            'text' => $text,
            'trials' => [],
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $trials
     * @return BotAnswer
     */
    private function result(string $text, array $trials = []): array
    {
        return [
            'text' => $text,
            'trials' => array_map(fn (array $t) => ['id' => (int) $t['id'], 'trial_code' => (string) $t['trial_code']], $trials),
            'suggestions' => [],
        ];
    }
}
