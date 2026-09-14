<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `trials_review` table — one row per (trial, department, review
 * round). Minimal stub; Fase 3 (Review per departemen) will flesh this out.
 *
 * @property int $id
 * @property int $trial_id
 * @property string $department
 * @property int|null $reviewer_user_id
 * @property int $review_round
 * @property string $status
 * @property bool $is_required
 * @property string|null $reviewer_name
 * @property string|null $reviewer_email
 * @property string|null $comment
 * @property Carbon|null $reviewed_at
 * @property int $edit_count
 */
#[Fillable(['trial_id', 'department', 'reviewer_user_id', 'review_round', 'status', 'is_required', 'reviewer_name', 'reviewer_email', 'comment', 'reviewed_at', 'edit_count'])]
class TrialReview extends Model
{
    /**
     * A reviewer may revise their already-submitted comment this many times
     * before the review locks (see TrialReviewPolicy::update()).
     */
    public const MAX_EDITS = 3;

    protected $table = 'trials_review';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trial, $this>
     */
    public function trial(): BelongsTo
    {
        return $this->belongsTo(Trial::class, 'trial_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /**
     * Rows this user may act on: assigned to them specifically
     * (reviewer_user_id), or unassigned (legacy-created rows / rows from
     * before per-person assignment existed) and matching one of their review
     * departments — see User::reviewDepartmentsForUser(). Aliases a
     * since-renamed department code (e.g. legacy's "PRD") so historical rows
     * still resolve correctly (User::expandReviewDepartmentAliases()).
     *
     * @param  Builder<TrialReview>  $query
     * @return Builder<TrialReview>
     */
    public function scopeVisibleToReviewer(Builder $query, User $user): Builder
    {
        $departments = User::expandReviewDepartmentAliases($user->reviewDepartmentsForUser());

        return $query->where(function (Builder $q) use ($user, $departments) {
            $q->where('reviewer_user_id', $user->id);

            if ($departments) {
                $q->orWhere(function (Builder $q2) use ($departments) {
                    $q2->whereNull('reviewer_user_id')
                        ->whereIn(DB::raw('UPPER(TRIM(department))'), $departments);
                });
            }
        });
    }
}
