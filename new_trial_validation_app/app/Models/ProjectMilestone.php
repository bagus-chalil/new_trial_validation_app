<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property Carbon|null $due_date
 * @property string $status
 * @property string|null $note
 */
class ProjectMilestone extends Model
{
    protected $table = 'pm_milestones';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
