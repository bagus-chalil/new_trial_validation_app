<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Trial Validation v2.0 Tahap 4: a Mixing validation record (Case kind MIX).
 * Own `vd_mixings` table, never trials_header (the legacy app still uses it).
 *
 * @property int $id
 * @property string $mixing_code
 * @property int|null $case_id
 * @property int|null $product_id
 * @property string $product_name
 * @property string $product_type
 * @property string $progress_status
 * @property string $current_step
 * @property int $revision_no
 * @property string $pending_with
 * @property int|null $approver_user_id
 * @property string|null $final_decision
 * @property string $created_by
 * @property Carbon|null $mixing_date
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property string|null $approved_by
 * @property string|null $rejected_by
 * @property string|null $approval_comment
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 */
class Mixing extends Model
{
    protected $table = 'vd_mixings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'batch_size_kg' => 'decimal:2',
            'mixing_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'deleted_at' => 'datetime',
            'revision_no' => 'integer',
        ];
    }

    /** @return HasMany<MixingResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(MixingResult::class, 'mixing_id');
    }

    /** @return HasMany<MixingFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(MixingFile::class, 'mixing_id');
    }

    /** @return HasMany<MixingReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(MixingReview::class, 'mixing_id');
    }

    public function currentReviewRound(): int
    {
        return (int) $this->revision_no + 1;
    }

    public function isOwner(User $user): bool
    {
        return trim((string) $this->created_by) !== ''
            && strcasecmp(trim((string) $this->created_by), trim((string) $user->email)) === 0;
    }
}
