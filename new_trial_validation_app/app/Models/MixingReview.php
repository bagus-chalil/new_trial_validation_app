<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mixing_id
 * @property string $department
 * @property int $review_round
 * @property string $status
 * @property int|null $reviewer_user_id
 * @property string|null $reviewer_name
 * @property string|null $comment
 * @property Carbon|null $reviewed_at
 */
class MixingReview extends Model
{
    protected $table = 'vd_mixing_reviews';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Mixing, $this> */
    public function mixing(): BelongsTo
    {
        return $this->belongsTo(Mixing::class, 'mixing_id');
    }
}
