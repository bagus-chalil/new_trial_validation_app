<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reusable list of stages (name, default duration in days) used to seed a new project.
 *
 * @property int $id
 * @property string $name
 * @property array<int, array<string, mixed>>|null $stages
 */
class ProjectStageTemplate extends Model
{
    protected $table = 'pm_stage_templates';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['stages' => 'array'];
    }
}
