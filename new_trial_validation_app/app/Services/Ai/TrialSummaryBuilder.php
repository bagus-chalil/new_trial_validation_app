<?php

namespace App\Services\Ai;

use App\Models\Trial;
use App\Models\TrialResult;
use App\Models\TrialReview;
use App\Models\TrialWeighing;
use App\Models\User;
use App\Models\ValidationParameter;

/**
 * Rule-based digest of ONE trial for the approver / department reviewer (no LLM). The points are
 * always shown on the Report Summary page; the same sentences are the FACTS sent to the Vision
 * Service when the user clicks "Tulis ringkasan AI", so the AI can only restate what is listed here.
 *
 * Anonymised on purpose: people (creator, reviewer, approver, e-mail) are never included, only the
 * department code of a review. Photos/files are never sent, only their count. Product identity
 * follows the same switch as `ai:export` (`ai.anonymize_product`). Free-text remarks and review
 * comments are shortened but not scrubbed (same known limit as ai:export).
 *
 * What counts as a "finding" was set against real data (2026-11, ~330 trials): NOT OK is rare (about
 * 1.5% of validation rows) so every NOT OK row is a finding; N/A is common (about 42%) and weighing
 * is skipped on most trials, so neither is a finding; most review comments start with "OK", so only
 * the text after that is treated as a note worth surfacing.
 */
class TrialSummaryBuilder
{
    public const TONE_FINDING = 'finding';

    public const TONE_INFO = 'info';

    private const MAX_NOT_OK = 10;

    private const MAX_NOTES = 10;

    private const FIELD_CHARS = 120;

    private const NOTE_CHARS = 200;

    /** The Vision Service refuses a fact longer than 600 characters. */
    private const FACT_CHARS = 500;

    /** Who gets the summary card: approvers and department reviewers (existing role helpers, no new rule). */
    public static function audience(User $user): bool
    {
        return $user->canApproveTrials() || $user->isReviewer();
    }

    /**
     * @return list<array{tone: string, text: string}>
     */
    public function build(Trial $trial): array
    {
        $points = [];
        $add = function (string $tone, string $key, array $replace = []) use (&$points): void {
            $points[] = ['tone' => $tone, 'text' => mb_substr(__($key, $replace), 0, self::FACT_CHARS)];
        };

        $dash = fn (?string $v): string => $v !== null && trim($v) !== '' ? trim($v) : '-';

        // Identity
        $identity = [
            'code' => $trial->trial_code,
            'type' => $dash($trial->product_type),
            'category' => $dash($trial->validation_category),
            'risk' => $dash($trial->risk_level),
        ];
        if (config('ai.anonymize_product')) {
            $add(self::TONE_INFO, 'insight.facts.identity_anonymous', $identity);
        } else {
            $add(self::TONE_INFO, 'insight.facts.identity', $identity + ['product' => $dash($trial->product_name)]);
        }

        // Status, round and revision
        $add(self::TONE_INFO, 'insight.facts.status', [
            'status' => $dash($trial->progress_status),
            'step' => $dash($trial->current_step),
            'round' => $trial->currentReviewRound(),
        ]);
        if ((int) $trial->revision_no > 0) {
            $add(self::TONE_INFO, 'insight.facts.revised', ['count' => (int) $trial->revision_no]);
        }
        if (in_array($trial->progress_status, ['Approved', 'Rejected', 'Need Revision'], true) && trim((string) $trial->approval_comment) !== '') {
            $add(self::TONE_INFO, 'insight.facts.decision_note', ['note' => $this->shorten($trial->approval_comment, self::NOTE_CHARS)]);
        }

        $this->validationPoints($trial, $add, $dash);
        $this->weighingPoints($trial, $add);

        $photos = $trial->attachments()->whereNull('deleted_at')->count();
        $photos > 0
            ? $add(self::TONE_INFO, 'insight.facts.photos', ['count' => $photos])
            : $add(self::TONE_INFO, 'insight.facts.photos_none');

        $this->reviewPoints($trial, $add);

        return $points;
    }

    /** @param  callable(string, string, array<string, mixed>=): void  $add */
    private function validationPoints(Trial $trial, callable $add, callable $dash): void
    {
        $parameterIds = ValidationParameter::query()->where('product_type', $trial->product_type)->pluck('id');
        $results = TrialResult::query()
            ->where('trial_id', $trial->id)
            ->whereIn('parameter_id', $parameterIds)
            ->with('parameter')
            ->get()
            ->sortBy(fn (TrialResult $r) => sprintf('%010d-%010d', $r->parameter->sort_order, $r->parameter_id))
            ->values();

        $total = $parameterIds->count();
        if ($total === 0 || $results->isEmpty()) {
            $add(self::TONE_INFO, 'insight.facts.validation_none');

            return;
        }

        $notOk = $results->where('decision', 'NOT OK');
        $add(self::TONE_INFO, 'insight.facts.validation_counts', [
            'ok' => $results->where('decision', 'OK')->count(),
            'total' => $total,
            'not_ok' => $notOk->count(),
            'na' => $results->where('decision', 'N/A')->count(),
        ]);

        $missing = $total - $results->count();
        if ($missing > 0) {
            $add(self::TONE_INFO, 'insight.facts.validation_missing', ['count' => $missing]);
        }

        foreach ($notOk->take(self::MAX_NOT_OK) as $row) {
            $add(self::TONE_FINDING, 'insight.facts.not_ok', [
                'parameter' => $row->parameter->parameter_name,
                'result' => $dash($this->shorten((string) $row->result_value, self::FIELD_CHARS)),
                'remark' => $dash($this->shorten((string) $row->remark, self::FIELD_CHARS)),
            ]);
        }
        if ($notOk->count() > self::MAX_NOT_OK) {
            $add(self::TONE_FINDING, 'insight.facts.not_ok_more', ['count' => $notOk->count() - self::MAX_NOT_OK]);
        }
    }

    /** @param  callable(string, string, array<string, mixed>=): void  $add */
    private function weighingPoints(Trial $trial, callable $add): void
    {
        $bySection = TrialWeighing::query()->where('trial_id', $trial->id)->orderBy('item_no')->get()->groupBy('section');

        foreach (['Packaging', 'Filling'] as $section) {
            $items = $bySection->get($section, collect());
            if ($items->isEmpty()) {
                $add(self::TONE_INFO, 'insight.facts.weighing_none', ['section' => $section]);

                continue;
            }

            $stats = TrialWeighing::statsForSection($items);
            if ($stats['count'] === 0) {
                // The only rows are the skip sentinel: skipping is routine, never a finding.
                $add(self::TONE_INFO, 'insight.facts.weighing_skipped', ['section' => $section]);

                continue;
            }

            $add(self::TONE_INFO, 'insight.facts.weighing_data', [
                'section' => $section,
                'count' => $stats['count'],
                'avg' => round((float) $stats['avg'], 3),
                'min' => round((float) $stats['min'], 3),
                'max' => round((float) $stats['max'], 3),
            ]);
        }
    }

    /** @param  callable(string, string, array<string, mixed>=): void  $add */
    private function reviewPoints(Trial $trial, callable $add): void
    {
        $pending = [];
        $plainOk = [];
        $notes = [];

        foreach ($trial->reviewStatusByDepartment() as $department => $entry) {
            /** @var TrialReview|null $review */
            $review = $entry['review'];
            if ($review === null) {
                continue;
            }

            if ($review->status === 'Pending') {
                $pending[] = $department;

                continue;
            }

            $note = self::noteBeyondOk((string) $review->comment);
            $note === '' ? $plainOk[] = $department : $notes[$department] = $note;
        }

        if ($pending !== []) {
            $add(self::TONE_INFO, 'insight.facts.review_pending', ['departments' => implode(', ', $pending)]);
        }
        if ($plainOk !== []) {
            $add(self::TONE_INFO, 'insight.facts.review_ok', ['departments' => implode(', ', $plainOk)]);
        }
        foreach (array_slice($notes, 0, self::MAX_NOTES, true) as $department => $note) {
            $add(self::TONE_FINDING, 'insight.facts.review_note', ['department' => $department, 'note' => $this->shorten($note, self::NOTE_CHARS)]);
        }
        if (count($notes) > self::MAX_NOTES) {
            $add(self::TONE_FINDING, 'insight.facts.review_more', ['count' => count($notes) - self::MAX_NOTES]);
        }
    }

    /**
     * The part of a review comment that says more than "OK": a leading ok/oke/okay (with its
     * punctuation) is stripped, so "OK" and "oke." come back as '' while "OK - production must ..."
     * comes back as the note itself. A comment that never started with OK is returned whole.
     */
    public static function noteBeyondOk(string $comment): string
    {
        $comment = trim(preg_replace('/\s+/', ' ', $comment) ?? '');

        return trim(preg_replace('/^(ok|oke|okay)\b[\s,.:;!\-–—>]*/iu', '', $comment) ?? '');
    }

    private function shorten(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)).'…' : $text;
    }
}
