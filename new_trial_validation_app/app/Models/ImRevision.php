<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One revision of an IM master. Only an `Effective` revision may be used by
 * IPC; Draft / review / Scheduled never are. At most one revision per master
 * is Effective at a time (enforced in ImRelease).
 *
 * @property int $id
 * @property int $master_id
 * @property int $revision_no
 * @property string $im_number
 * @property int|null $im_number_id
 * @property string $status
 * @property string $description
 * @property string $appearance
 * @property string $mfg_category
 * @property string $customer
 * @property string $customer_ref
 * @property string $line_label
 * @property Carbon|null $issue_date
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_at
 * @property Carbon|null $superseded_at
 * @property string $revision_reason
 * @property string|null $replaces_number
 * @property array<string, mixed>|null $content
 * @property string|null $legacy_reference
 * @property bool $is_onboarded
 * @property int|null $prepared_by_user_id
 * @property string $prepared_by_name
 * @property int|null $approver_user_id
 * @property string $pending_with
 * @property int $review_round
 * @property string|null $approval_comment
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 */
class ImRevision extends Model
{
    public const STATUSES = ['Draft', 'In Review', 'Ready for Approval', 'Need Revision', 'Rejected', 'Scheduled', 'Effective', 'Superseded'];

    /** Statuses in which QA may still edit the worksheet. */
    public const EDITABLE = ['Draft', 'Need Revision'];

    /** A master can have only one revision in these states at a time. */
    public const OPEN = ['Draft', 'In Review', 'Ready for Approval', 'Need Revision', 'Scheduled'];

    protected $table = 'vd_im_revisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'effective_from' => 'date',
            'effective_at' => 'datetime',
            'superseded_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'content' => 'array',
            'is_onboarded' => 'boolean',
            'revision_no' => 'integer',
            'review_round' => 'integer',
        ];
    }

    /** @return BelongsTo<ImMaster, $this> */
    public function master(): BelongsTo
    {
        return $this->belongsTo(ImMaster::class, 'master_id');
    }

    /** @return HasMany<ImParameter, $this> */
    public function parameters(): HasMany
    {
        return $this->hasMany(ImParameter::class, 'revision_id')->orderBy('section')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ImEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(ImEvidence::class, 'revision_id')->orderBy('id');
    }

    /** @return HasMany<ImFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ImFile::class, 'revision_id')->orderBy('id');
    }

    /** @return HasMany<ImSignoff, $this> */
    public function signoffs(): HasMany
    {
        return $this->hasMany(ImSignoff::class, 'revision_id')->orderBy('id');
    }

    /** @param Builder<ImRevision> $query */
    public function scopeEffective(Builder $query): void
    {
        $query->where('status', 'Effective');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    /**
     * Frozen copy of everything IPC needs to pin for a run (Tahap 7 uses it):
     * the rules at this revision, never affected by later revisions.
     *
     * @return array<string, mixed>
     */
    public function snapshotPayload(): array
    {
        return [
            'im_number' => $this->im_number,
            'revision_no' => $this->revision_no,
            'status' => $this->status,
            'effective_from' => $this->effective_from?->toDateString(),
            'header' => $this->only(['description', 'appearance', 'mfg_category', 'customer', 'customer_ref', 'line_label']),
            'content' => $this->content ?? [],
            'parameters' => $this->parameters->map(fn (ImParameter $p) => $p->only([
                'section', 'sort_order', 'code', 'name', 'category', 'description', 'specification', 'frequency', 'value_min', 'value_max', 'unit',
            ]))->values()->all(),
        ];
    }
}
