<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a project/stage/quotation. The physical file lives on the
 * `project_files` disk under `stored_name` (see config/filesystems.php).
 *
 * @property int $id
 * @property int|null $project_id
 * @property int|null $stage_id
 * @property string $context
 * @property string $stored_name
 * @property bool $is_missing
 */
class ProjectFile extends Model
{
    protected $table = 'pm_files';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'is_missing' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<ProjectStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }
}
