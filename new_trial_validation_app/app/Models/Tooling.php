<?php

namespace App\Models;

use App\Services\Projects\ToolingWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Check Tooling request (form FR.PRNI.002.00, Packaging Proposal to
 * Production Check). PRNI submits, RNI fills the formula, PIE receives the
 * sample and checks the tooling, Production signs off. The status is derived
 * by App\Services\Projects\ToolingWorkflow from the four approvals and the
 * sample status, except the manual "Revisi / Hold" and "Ditolak".
 *
 * @property int $id
 * @property string|null $legacy_id
 * @property string $doc_code
 * @property string $npd_no
 * @property Carbon|null $request_date
 * @property string $requested_by_name
 * @property int|null $submitted_by_user_id
 * @property string $submitted_by_name
 * @property Carbon|null $submitted_at
 * @property string $sample_status
 * @property Carbon|null $sample_send_date
 * @property string|null $sample_send_note
 * @property Carbon|null $sample_received_date
 * @property string|null $sample_received_note
 * @property int|null $sample_received_by_user_id
 * @property string $sample_received_by_name
 * @property Carbon|null $due_date
 * @property bool $is_critical
 * @property string $client_code
 * @property string $product_name
 * @property string $marketing
 * @property string $packdev
 * @property string $product_type
 * @property string $packaging_type
 * @property string $quantity_po
 * @property string $launch_date
 * @property string|null $dimension_packaging
 * @property string|null $material_packaging
 * @property string|null $bom_primary
 * @property string|null $pm_ref_detail
 * @property list<array{title: string, url: string}>|null $links
 * @property string|null $formula_info
 * @property string $volume_suggest
 * @property string $recommended_machine
 * @property string $need_invest
 * @property string|null $need_invest_note
 * @property string|null $production_note
 * @property string $risk_level
 * @property int|null $pi_owner_user_id
 * @property string $pi_owner_name
 * @property string $status
 * @property string|null $status_reason
 * @property int|null $project_id
 * @property array<int, array<string, mixed>>|null $history_log
 * @property array<string, int|null>|null $assignees
 * @property string $updated_by_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 */
class Tooling extends Model
{
    protected $table = 'pm_toolings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'submitted_at' => 'datetime',
            'sample_send_date' => 'date',
            'sample_received_date' => 'date',
            'due_date' => 'date',
            'is_critical' => 'boolean',
            'links' => 'array',
            'history_log' => 'array',
            'assignees' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Row-level visibility (2026-11-25): Admin and users whose department / review
     * unit takes part in the flow (PRNI, RNI, PIE/PI, Production) see every
     * request; anybody else only the requests they submitted themselves.
     *
     * @param  Builder<Tooling>  $query
     * @return Builder<Tooling>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (ToolingWorkflow::canSeeAll($user)) {
            return $query;
        }

        return $query->where('submitted_by_user_id', $user->id);
    }

    /** The user id assigned to sign this role's box, or null = anyone in the department group. */
    public function assigneeFor(string $role): ?int
    {
        $id = ($this->assignees ?? [])[$role] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /** @return HasMany<ToolingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ToolingItem::class, 'tooling_id')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ToolingApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(ToolingApproval::class, 'tooling_id');
    }

    /** @return HasMany<ProjectFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'tooling_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** The approval row of one role, or null when nobody has signed it yet. */
    public function approvalFor(string $role): ?ToolingApproval
    {
        return $this->approvals->firstWhere('role', $role);
    }

    public function isSigned(string $role): bool
    {
        return (bool) $this->approvalFor($role)?->approved;
    }

    public function hasSample(): bool
    {
        return in_array($this->sample_status, ['Sampel Diterima', 'Selesai Dicek'], true);
    }
}
