<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Projects module: a tracked project (machine purchase, improvement, tooling
 * NPD, ...). Merged from the standalone Project Tracking PIE app, see
 * ../../PROJECT_MODULE_PLAN.md. `pic_name` always holds the plain-text name
 * from the legacy app; `pic_user_id` is only set when it could be matched to a
 * real Trial-app user (never auto-creating accounts).
 *
 * @property int $id
 * @property string|null $legacy_id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property string $status
 * @property string $priority
 * @property int|null $pic_user_id
 * @property string $pic_name
 * @property int|null $created_by_user_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int|null $deleted_by
 * @property Carbon|null $updated_at
 * @property Carbon|null $end_date
 * @property int $progress
 * @property Carbon|null $deleted_at
 */
class Project extends Model
{
    protected $table = 'pm_projects';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'budget' => 'decimal:2',
            'actual' => 'decimal:2',
            'legacy_meta' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /** @return HasMany<ProjectStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class, 'project_id')->orderBy('sort_order');
    }

    /** @return HasMany<ProjectMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'project_id');
    }

    /** @return HasMany<ProjectQuotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(ProjectQuotation::class, 'project_id');
    }

    /** @return HasMany<ProjectFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'project_id');
    }

    /** @return HasMany<Improvement, $this> */
    public function improvements(): HasMany
    {
        return $this->hasMany(Improvement::class, 'project_id');
    }
}
