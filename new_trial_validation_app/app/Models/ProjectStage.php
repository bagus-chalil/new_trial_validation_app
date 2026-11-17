<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stage (task) of a project. `stage_type` is 'Validation' for stages named
 * like Validasi/Validation/Trial (Fase 4 links those to a Trial via `trial_id`).
 *
 * @property int $id
 * @property int $project_id
 * @property string $stage_type
 * @property string $name
 * @property int|null $trial_id
 */
class ProjectStage extends Model
{
    protected $table = 'pm_stages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'plan_start' => 'date',
            'plan_end' => 'date',
            'actual_start' => 'date',
            'actual_end' => 'date',
            'progress' => 'integer',
            'is_parallel' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<Trial, $this> */
    public function trial(): BelongsTo
    {
        return $this->belongsTo(Trial::class, 'trial_id');
    }

    /** @return HasMany<ProjectFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'stage_id');
    }
}
