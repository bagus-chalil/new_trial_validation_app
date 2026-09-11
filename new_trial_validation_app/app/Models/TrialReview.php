<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Legacy `trials_review` table — one row per (trial, department, review
 * round). Minimal stub; Fase 3 (Review per departemen) will flesh this out.
 *
 * @property int $id
 * @property int $trial_id
 * @property string $department
 * @property int $review_round
 * @property string $status
 * @property bool $is_required
 * @property string|null $reviewer_name
 * @property string|null $reviewer_email
 * @property string|null $comment
 * @property Carbon|null $reviewed_at
 * @property int $edit_count
 */
#[Fillable(['trial_id', 'department', 'review_round', 'status', 'is_required', 'reviewer_name', 'reviewer_email', 'comment', 'reviewed_at', 'edit_count'])]
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
}
