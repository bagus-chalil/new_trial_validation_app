<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An improvement suggestion on the Projects module's board. Status flow:
 * Baru, Review PI, Menunggu Data, Disetujui, Tidak Perlu Improvement, Selesai,
 * Ditolak. The after_* fields hold the result after the improvement was done
 * (condition, result, date, PIC, links; evidence files are phase=after).
 * Decision: Pending, To Project, Suggestion Only.
 *
 * @property int $id
 * @property string|null $legacy_id
 * @property string $title
 * @property int|null $submitted_by_user_id
 * @property string $status
 * @property string $decision
 * @property int|null $project_id
 * @property Carbon|null $submitted_at
 * @property array<int, string>|null $links
 * @property array<int, array<string, mixed>>|null $feedback_log
 * @property string|null $after_condition
 * @property string|null $after_result
 * @property Carbon|null $after_date
 * @property string $after_pic
 * @property array<int, mixed>|null $after_links
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 */
class Improvement extends Model
{
    /** 'Selesai' and 'Ditolak' were added by the production Project Tracking app after 17 Nov. */
    public const STATUSES = ['Baru', 'Review PI', 'Menunggu Data', 'Disetujui', 'Tidak Perlu Improvement', 'Selesai', 'Ditolak'];

    public const DECISIONS = ['Pending', 'To Project', 'Suggestion Only'];

    protected $table = 'pm_improvements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'links' => 'array',
            'feedback_log' => 'array',
            'after_links' => 'array',
            'after_date' => 'date',
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
