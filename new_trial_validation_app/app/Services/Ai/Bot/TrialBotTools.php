<?php

namespace App\Services\Ai\Bot;

use App\Models\AiLesson;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Read-only question tools for the rule-based trial bot. Deliberately knows
 * nothing about wording or languages: every tool returns plain data and the
 * answer templates (or, later, an LLM) turn it into sentences. Anything that
 * touches individual trials goes through Trial::scopeVisibleTo() — the same
 * row-level scoping every list page uses — so the bot can never reveal a trial
 * the asking user could not open. Lesson-based tools return only aggregates
 * (rates over >= 5 trials), never individual trials.
 */
class TrialBotTools
{
    private const LIST_LIMIT = 5;

    private const MAX_LIMIT = 10;

    /**
     * What is waiting for this user: own drafts / revisions, plus review and
     * approval work addressed to them (same definitions as the Dashboard's
     * My Work section).
     *
     * @return array{draft:int, need_revision:int, in_progress:int, pending_reviews:int, pending_approvals:int}
     */
    public function myQueue(User $user): array
    {
        $own = fn () => Trial::query()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(created_by)) = ?', [strtolower(trim($user->email))]);

        $pendingReviews = 0;
        if ($user->isReviewer()) {
            $pendingReviews = TrialReview::query()
                ->join('trials_header as h', 'h.id', '=', 'trials_review.trial_id')
                ->where('h.progress_status', 'In Review')
                ->whereNull('h.deleted_at')
                ->whereRaw('trials_review.review_round = h.revision_no + 1')
                ->where('trials_review.status', 'Pending')
                ->visibleToReviewer($user)
                ->count();
        }

        $pendingApprovals = 0;
        if ($user->canApproveTrials()) {
            $pendingApprovals = Trial::query()
                ->where('progress_status', 'Ready for Approval')
                ->whereNull('deleted_at')
                ->where('approver_user_id', $user->id)
                ->count();
        }

        return [
            'draft' => $own()->where('progress_status', 'Draft')->count(),
            'need_revision' => $own()->where('progress_status', 'Need Revision')->count(),
            'in_progress' => $own()->whereIn('progress_status', ['In Review', 'Ready for Approval'])->count(),
            'pending_reviews' => $pendingReviews,
            'pending_approvals' => $pendingApprovals,
        ];
    }

    /**
     * Trials visible to the user, counted (and the newest few listed) by
     * status and optional product type.
     *
     * @return array{status:?string, product_type:?string, total:int, trials:array<int, array<string,mixed>>}
     */
    public function trialsByStatus(User $user, ?string $status = null, ?string $productType = null, int $limit = self::LIST_LIMIT): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));
        $query = fn () => Trial::query()->visibleTo($user)
            ->when($status !== null, fn ($q) => $q->where('trials_header.progress_status', $status))
            ->when($productType !== null, fn ($q) => $q->where('trials_header.product_type', $productType));

        return [
            'status' => $status,
            'product_type' => $productType,
            'total' => $query()->count('trials_header.id'),
            'trials' => $query()
                ->orderByDesc('trials_header.created_at')->orderByDesc('trials_header.id')
                ->limit($limit)
                ->get()
                ->map(fn (Trial $t) => $this->trialRow($t))
                ->all(),
        ];
    }

    /**
     * Counts per status of everything the user can see (same numbers as the
     * Dashboard's summary cards).
     *
     * @return array<string, int>
     */
    public function summary(User $user): array
    {
        return Trial::summaryCounts($user);
    }

    /**
     * The user's own trials (created_by), optionally by status — still run
     * through visibleTo() so nothing outside their access can surface.
     *
     * @return array{status:?string, product_type:?string, total:int, trials:array<int, array<string,mixed>>}
     */
    public function myTrials(User $user, ?string $status = null, ?string $productType = null): array
    {
        $query = fn () => Trial::query()->visibleTo($user)
            ->whereRaw('LOWER(TRIM(trials_header.created_by)) = ?', [strtolower(trim($user->email))])
            ->when($status !== null, fn ($q) => $q->where('trials_header.progress_status', $status))
            ->when($productType !== null, fn ($q) => $q->where('trials_header.product_type', $productType));

        return [
            'status' => $status,
            'product_type' => $productType,
            'total' => $query()->count('trials_header.id'),
            'trials' => $query()
                ->orderByDesc('trials_header.created_at')->orderByDesc('trials_header.id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (Trial $t) => $this->trialRow($t))
                ->all(),
        ];
    }

    /**
     * Visible trials whose code, product name or finish good code contains
     * the keyword (LIKE wildcards in the keyword are escaped).
     *
     * @return array{keyword:string, total:int, trials:array<int, array<string,mixed>>}
     */
    public function searchTrials(User $user, string $keyword): array
    {
        $keyword = trim($keyword);
        $like = '%'.addcslashes($keyword, '\%_').'%';
        $query = fn () => Trial::query()->visibleTo($user)
            ->where(fn ($q) => $q
                ->where('trials_header.trial_code', 'like', $like)
                ->orWhere('trials_header.product_name', 'like', $like)
                ->orWhere('trials_header.finish_good_code', 'like', $like));

        return [
            'keyword' => $keyword,
            'total' => $query()->count('trials_header.id'),
            'trials' => $query()
                ->orderByDesc('trials_header.created_at')->orderByDesc('trials_header.id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (Trial $t) => $this->trialRow($t))
                ->all(),
        ];
    }

    /**
     * Where one trial is right now. Null when it does not exist OR the user
     * may not see it — the two cases are deliberately indistinguishable.
     *
     * @return array<string, mixed>|null
     */
    public function trialStatus(User $user, string $trialCode): ?array
    {
        $trial = Trial::query()->visibleTo($user)
            ->where('trials_header.trial_code', trim($trialCode))
            ->first();

        return $trial === null ? null : $this->trialRow($trial) + [
            'current_step' => $trial->current_step,
            'revision_no' => (int) $trial->revision_no,
            'final_decision' => $trial->final_decision,
        ];
    }

    /**
     * Visible trials that have sat in the process (In Review / Ready for
     * Approval) without any update for at least $days days, oldest first.
     *
     * @return array{days:int, total:int, trials:array<int, array<string,mixed>>}
     */
    public function stuckTrials(User $user, int $days = 14): array
    {
        $days = max(1, $days);
        $query = fn () => Trial::query()->visibleTo($user)
            ->whereIn('trials_header.progress_status', ['In Review', 'Ready for Approval'])
            ->where('trials_header.updated_at', '<', Carbon::now()->subDays($days));

        return [
            'days' => $days,
            'total' => $query()->count('trials_header.id'),
            'trials' => $query()
                ->orderBy('trials_header.updated_at')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (Trial $t) => $this->trialRow($t))
                ->all(),
        ];
    }

    /**
     * Validation parameters that most often come out NOT OK (learned
     * `hotspot` lessons only), optionally narrowed to one product type.
     *
     * @return array{product_type:?string, parameters:array<int, array<string,mixed>>}
     */
    public function notOkHotspots(?string $productType = null): array
    {
        $lessons = AiLesson::query()
            ->where('kind', AiLesson::KIND_NOT_OK_PATTERN)
            ->where('status', AiLesson::STATUS_ACTIVE)
            ->get()
            ->filter(fn (AiLesson $l) => ($l->payload['hotspot'] ?? false) === true)
            ->when($productType !== null, fn ($c) => $c->filter(
                fn (AiLesson $l) => mb_strtolower((string) ($l->payload['product_type'] ?? '')) === mb_strtolower($productType)
            ))
            ->sortByDesc(fn (AiLesson $l) => $l->payload['not_ok_rate'] ?? 0)
            ->take(self::LIST_LIMIT);

        return [
            'product_type' => $productType,
            'parameters' => $lessons->map(fn (AiLesson $l) => [
                'product_type' => $l->payload['product_type'] ?? null,
                'parameter_name' => $l->payload['parameter_name'] ?? null,
                'not_ok_trials' => $l->payload['not_ok_trials'] ?? 0,
                'n_trials' => $l->payload['n_trials'] ?? 0,
                'not_ok_rate' => $l->payload['not_ok_rate'] ?? 0,
                'confidence' => $l->confidence,
            ])->values()->all(),
        ];
    }

    /**
     * Normal spread between weighing samples of one trial (coefficient of
     * variation) for a product type + section, from the learned lessons.
     * Null when nothing reliable has been learned for that combination.
     *
     * @return array<string, mixed>|null
     */
    public function weighingNorm(string $productType, string $section): ?array
    {
        $lesson = AiLesson::query()
            ->where('kind', AiLesson::KIND_WEIGHING_CONSISTENCY)
            ->where('status', AiLesson::STATUS_ACTIVE)
            ->where('scope_key', trim($productType).'|'.trim($section))
            ->first();

        return $lesson === null ? null : [
            'product_type' => $lesson->payload['product_type'] ?? $productType,
            'section' => $lesson->payload['section'] ?? $section,
            'median_cv' => $lesson->payload['median_cv'] ?? null,
            'high_cv' => $lesson->payload['high_cv'] ?? null,
            'n_trials' => $lesson->payload['n_trials'] ?? 0,
            'confidence' => $lesson->confidence,
        ];
    }

    /** @return array<string, mixed> */
    private function trialRow(Trial $trial): array
    {
        return [
            'id' => $trial->id,
            'trial_code' => $trial->trial_code,
            'product_name' => $trial->product_name,
            'product_type' => $trial->product_type,
            'progress_status' => $trial->progress_status,
            'pending_with' => $trial->pending_with,
            'updated_at' => $trial->updated_at?->toDateTimeString(),
        ];
    }
}
