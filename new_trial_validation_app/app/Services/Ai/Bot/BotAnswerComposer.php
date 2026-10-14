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

    /** @return BotAnswer */
    private function withExamples(string $text): array
    {
        $suggestions = array_map(
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
