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
 * One *editable* ("current") row per trial at a time (is_locked=false,
 * enforced by unique(trial_id, version) plus the app only ever writing one
 * unlocked row) — see Trial::lineConfigurationReport()/lineConfigurationReportVersions().
 *
 * Anyone on the PROD review team may start or claim a report for a trial
 * that doesn't have a recorded drafter yet, but once one is recorded
 * (`blocksEditFor()`, backed by `updated_by_user_id`), only that drafter —
 * or Admin — may keep editing it; being on the PROD team is not itself a
 * blanket edit right on every trial's report (see
 * SaveTrialLineConfigurationReportRequest and TrialReportController::show()).
 *
 * Deliberately has no locking tied to the *trial's* status (no dependency on
 * progress_status/final_decision) — the current version stays editable
 * regardless of whether the trial itself has been decided.
 *
 * It IS locked, though, by its own maker-checker approval chain (added
 * 2026-09-16, same day as the assignment/versioning follow-up below):
 * assigning a specific user to Approved(PIE) and/or Checked(PROD)
 * (`isSubmittedForApproval()`) submits the report and freezes it from
 * further editing by the maker (PROD reviewer/Admin) — see
 * SaveTrialLineConfigurationReportRequest::authorize() — until either an
 * Admin overrides, or it's Returned. The two checks are sequential
 * (`currentApprovalStage()`): Checked(PROD) can't be confirmed until
 * Approved(PIE) is. Return (see ReturnTrialLineConfigurationReport) is
 * available to whichever stage is currently active and immediately locks
 * this row as an immutable, download-only historical version, cloning a
 * fresh current version forward with the same content but every sign-off
 * field (approvals, assignments, return state) cleared — a full new review
 * cycle, not a silent continuation of stale assignments.
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
 * @property bool $approved_pie
 * @property string|null $approved_pie_by
 * @property Carbon|null $approved_pie_at
 * @property int|null $approved_pie_user_id
 * @property string|null $approved_pie_label
 * @property bool $checked_prod
 * @property string|null $checked_prod_by
 * @property Carbon|null $checked_prod_at
 * @property int|null $checked_prod_user_id
 * @property string|null $checked_prod_label
 * @property bool $return_prod
 * @property string|null $return_prod_by
 * @property Carbon|null $return_prod_at
 * @property string|null $return_reason
 * @property int|null $updated_by_user_id
 * @property int $version
 * @property bool $is_locked
 * @property Carbon|null $locked_at
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
    'approved_pie',
    'approved_pie_by',
    'approved_pie_at',
    'approved_pie_user_id',
    'approved_pie_label',
    'checked_prod',
    'checked_prod_by',
    'checked_prod_at',
    'checked_prod_user_id',
    'checked_prod_label',
    'return_prod',
    'return_prod_by',
    'return_prod_at',
    'return_reason',
    'updated_by_user_id',
    'version',
    'is_locked',
    'locked_at',
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
            'approved_pie' => 'boolean',
            'approved_pie_at' => 'datetime',
            'checked_prod' => 'boolean',
            'checked_prod_at' => 'datetime',
            'return_prod' => 'boolean',
            'return_prod_at' => 'datetime',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedPieUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_pie_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedProdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_prod_user_id');
    }

    /**
     * True once at least one of Approved(PIE)/Checked(PROD) has an assigned
     * user — i.e. this report has been submitted into the approval chain,
     * which is what freezes it from further edits by the maker (see
     * SaveTrialLineConfigurationReportRequest::authorize()).
     */
    public function isSubmittedForApproval(): bool
    {
        return ! empty($this->approved_pie_user_id) || ! empty($this->checked_prod_user_id);
    }

    /**
     * True when this report already has a recorded drafter
     * (updated_by_user_id — whoever last saved its content) and $user isn't
     * them. A report with no drafter recorded yet (e.g. one seeded directly
     * rather than through the normal save action) is open to any PROD-team
     * member to claim — being on the PROD team only grants the right to
     * start or claim a report nobody has drafted yet, not to edit someone
     * else's already-claimed draft.
     */
    public function blocksEditFor(User $user): bool
    {
        return ! empty($this->updated_by_user_id) && (int) $this->updated_by_user_id !== $user->id;
    }

    /**
     * Which sign-off is currently active in the maker-checker chain —
     * Approved(PIE) always comes before Checked(PROD). Null once both are
     * done (or once neither was ever assigned).
     *
     * @return 'approved_pie'|'checked_prod'|null
     */
    public function currentApprovalStage(): ?string
    {
        if (! $this->approved_pie) {
            return 'approved_pie';
        }

        if (! $this->checked_prod) {
            return 'checked_prod';
        }

        return null;
    }

    /**
     * The user id assigned to whichever stage is currently active, or null
     * if nothing is currently assignable/actionable (fully done, or the
     * active stage has no assignee yet).
     */
    public function currentStageUserId(): ?int
    {
        return match ($this->currentApprovalStage()) {
            'approved_pie' => $this->approved_pie_user_id,
            'checked_prod' => $this->checked_prod_user_id,
            default => null,
        };
    }
}
