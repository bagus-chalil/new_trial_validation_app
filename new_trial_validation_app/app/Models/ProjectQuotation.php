<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $vendor_name
 * @property int|null $file_id
 * @property Carbon|null $quotation_date
 * @property Carbon|null $valid_until
 * @property string $quotation_no
 * @property string $currency
 * @property string $status
 * @property string|null $note
 * @property string $link
 */
class ProjectQuotation extends Model
{
    protected $table = 'pm_quotations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quotation_date' => 'date',
            'valid_until' => 'date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<ProjectFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'file_id');
    }
}
