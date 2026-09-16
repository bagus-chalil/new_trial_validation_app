<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The "Line Configuration Report" — a production-line-setup/trial-result
 * sheet (previously kept only as an ad-hoc Excel file, see the 2026-09-16
 * feature request) filled in by the PROD department while reviewing a trial.
 * One row per trial (unique trial_id).
 *
 * Deliberately has no locking of any kind (no status field, no edit-count
 * cap like TrialReview::MAX_EDITS, no dependency on the trial's
 * progress_status/final_decision) — the user explicitly asked for this form
 * to stay editable indefinitely, even after the trial has been decided,
 * since only the PROD reviewer/an admin can touch it anyway
 * (see the `manage-line-configuration-report` Gate).
 *
 * @property int $id
 * @property int $trial_id
 * @property Carbon|null $report_date
 * @property string|null $client_name
 * @property string|null $pic
 * @property string|null $operator
 * @property string|null $validation_name
 * @property string|null $total_qty
 * @property string|null $setting_qty
 * @property string|null $pass_qty
 * @property string|null $ng_qty
 * @property string|null $capacity_label
 * @property array<int, array<string, mixed>>|null $production_standard
 * @property array<int, array<string, mixed>>|null $line_configuration
 * @property string|null $opinion
 * @property int|null $updated_by_user_id
 */
#[Fillable([
    'trial_id',
    'report_date',
    'client_name',
    'pic',
    'operator',
    'validation_name',
    'total_qty',
    'setting_qty',
    'pass_qty',
    'ng_qty',
    'capacity_label',
    'production_standard',
    'line_configuration',
    'opinion',
    'updated_by_user_id',
])]
class TrialLineConfigurationReport extends Model
{
    protected $table = 'trial_line_configuration_reports';

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'production_standard' => 'array',
            'line_configuration' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Trial, $this>
     */
    public function trial(): BelongsTo
    {
        return $this->belongsTo(Trial::class, 'trial_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
