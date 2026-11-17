<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An improvement suggestion on the Projects module's board. Status flow:
 * Baru, Review PI, Menunggu Data, Disetujui, Tidak Perlu Improvement.
 * Decision: Pending, To Project, Suggestion Only.
 *
 * @property int $id
 * @property string|null $legacy_id
 * @property string $title
 * @property int|null $submitted_by_user_id
 * @property string $status
 * @property string $decision
 * @property int|null $project_id
 * @property Carbon|null $deleted_at
 */
class Improvement extends Model
{
    public const STATUSES = ['Baru', 'Review PI', 'Menunggu Data', 'Disetujui', 'Tidak Perlu Improvement'];

    public const DECISIONS = ['Pending', 'To Project', 'Suggestion Only'];

    protected $table = 'pm_improvements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'links' => 'array',
            'feedback_log' => 'array',
            'submitted_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return HasMany<ImprovementFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ImprovementFile::class, 'improvement_id');
    }
}
