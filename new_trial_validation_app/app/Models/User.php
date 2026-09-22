<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Maps onto the legacy `users` table, shared with the old PHP app (see
 * ../../../CLAUDE.md and MIGRATION_PLAN.md §4). Schema is
 * id/name/email/password_hash/role/department/is_active/created_at/deleted_at/deleted_by
 * — no `password`, `updated_at`, `email_verified_at`, or `remember_token`
 * columns, so several Authenticatable defaults are overridden below.
 *
 * Role/department helpers here are a port of the authorization primitives in
 * the legacy app's app/bootstrap.php (is_admin(), is_staff(), is_reviewer(),
 * reviewer_department_codes(), etc.) — see App\Policies\TrialPolicy and
 * Trial::scopeVisibleTo() for where they're used.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password_hash
 * @property string $role
 * @property string|null $app_role
 * @property string|null $department
 * @property string|null $review_unit
 * @property int|null $review_team_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 */
#[Fillable(['name', 'email', 'role', 'app_role', 'department', 'review_unit', 'review_team_id'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * Legacy `users` table has no remember_token column.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Port of display_person_name() (app/bootstrap.php:42-54): several
     * report-facing columns (approved_by/rejected_by/reviewer_name) can hold
     * a bare email address on rows the still-live legacy app itself wrote —
     * this resolves it to that user's real name when possible, else returns
     * the value as-is.
     */
    public static function displayName(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $name = trim((string) self::query()->where('email', $value)->value('name'));
            if ($name !== '') {
                return $name;
            }
        }

        return $value;
    }

    public static function normalizeDepartment(?string $dept): string
    {
        $dept = strtoupper(trim((string) $dept));

        return preg_replace('/\s+/', ' ', $dept) ?? '';
    }

    /**
     * Legacy `role` values that bake a department into the role name
     * (bootstrap.php hardcodes literal checks against these — see
     * effectiveRole()) — mapped onto the generic 'Manager' tier for this
     * app's own authorization, without ever touching the shared `role`
     * column those legacy checks still rely on.
     */
    private const LEGACY_ROLE_TO_GENERIC = [
        'Manager QAC' => 'Manager',
        'Team Leader' => 'Manager',
        'Part Leader' => 'Manager',
        'Team Leader QA' => 'Manager',
        'Team Leader Production' => 'Manager',
    ];

    /**
     * Phase 2 of the RBAC/Team-master redesign (see memory
     * rbac_team_lane_redesign_2026_09_22): this app's authoritative role
     * tier. Prefers the this-app-owned `app_role` column when set; falls
     * back to mapping the legacy-shared `role` column through
     * LEGACY_ROLE_TO_GENERIC otherwise, so a user created (by either app)
     * before ever being touched in Access Rights still resolves correctly.
     * The legacy-shared `role` column itself is never mutated by this app's
     * Access Rights screen going forward — only `app_role` is.
     */
    public function effectiveRole(): string
    {
        $appRole = trim((string) $this->app_role);
        if ($appRole !== '') {
            return $appRole;
        }

        return self::mapLegacyRoleToGeneric($this->role);
    }

    public static function mapLegacyRoleToGeneric(?string $role): string
    {
        $role = trim((string) $role);

        return self::LEGACY_ROLE_TO_GENERIC[$role] ?? $role;
    }

    /**
     * Applies an "effective role is one of $roles" constraint to a query
     * builder (Eloquent or plain, both support where()/whereIn()/orWhere())
     * — the DB-level counterpart of effectiveRole(), for queries that can't
     * load every row into memory first (approver pickers, Rule::exists()
     * checks). Every existing user has `app_role` backfilled by the Phase 2
     * migration, so the `role`-based fallback branch only ever matters for
     * a user created after that migration ran and never yet saved through
     * Access Rights.
     *
     * @param  \Illuminate\Database\Query\Builder|Builder<User>  $query
     * @param  list<string>  $roles
     */
    public static function applyEffectiveRoleIn($query, array $roles): void
    {
        // The app_role-is-null fallback branch must match effectiveRole()'s
        // own fallback exactly: a legacy role string maps through
        // LEGACY_ROLE_TO_GENERIC first, so e.g. a not-yet-migrated
        // 'Manager QAC' row (app_role still null) matches a search for
        // 'Manager', not just a literal 'Manager' string in `role`.
        $rawRoleMatches = $roles;
        foreach (self::LEGACY_ROLE_TO_GENERIC as $legacyRole => $genericRole) {
            if (in_array($genericRole, $roles, true) && ! in_array($legacyRole, $rawRoleMatches, true)) {
                $rawRoleMatches[] = $legacyRole;
            }
        }

        $query->where(function ($q) use ($roles, $rawRoleMatches) {
            $q->whereIn('app_role', $roles)
                ->orWhere(function ($q2) use ($rawRoleMatches) {
                    $q2->whereNull('app_role')->whereIn('role', $rawRoleMatches);
                });
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->effectiveRole() === 'Super Admin';
    }

    public function isAdmin(): bool
    {
        return $this->effectiveRole() === 'Admin' || $this->isSuperAdmin();
    }

    public function isStaff(): bool
    {
        return $this->effectiveRole() === 'Staff' || $this->isAdmin();
    }

    public function isViewer(): bool
    {
        return $this->effectiveRole() === 'Viewer';
    }

    /**
     * Roles structurally eligible to be assigned as a trial's approver —
     * used to restrict the approver picker on the Review & Submit page
     * (wizard Step 6) and to re-validate approver_user_id server-side, so a
     * Staff/Viewer/etc. user can never be picked as an approver only to find
     * they have no way to act on it afterward (canApproveTrials() gates the
     * "Need Approval" sidebar entry on this same role set, plus an existing
     * assignment — see canApproveTrials() below).
     *
     * Narrowed 2026-09-22 (Phase 2 of the RBAC/Team-master redesign) from
     * the previous 8-entry legacy-role-string list to the 3 generic tiers
     * structurally eligible to hold final approval authority — every
     * legacy department-coupled role string (Manager QAC, Team Leader,
     * Part Leader, Team Leader QA, Team Leader Production) now maps onto
     * 'Manager' via effectiveRole()/applyEffectiveRoleIn(), so this list
     * stays exhaustive without needing to enumerate them here too.
     *
     * @return list<string>
     */
    public static function approverEligibleRoles(): array
    {
        return ['Manager', 'Admin', 'Super Admin'];
    }

    public function departmentCode(): string
    {
        $dept = self::normalizeDepartment($this->department ?? '');

        return $dept !== '' ? $dept : self::normalizeDepartment($this->role);
    }

    /**
     * Legacy review-team codes that were renamed going forward in this app
     * (see the 2026-09-14 `review_unit` migration doc comment) — kept here
     * so a `trials_review` row written before the rename (department='PRD')
     * still resolves to the same reviewer group as one written after
     * (review_unit='PROD'). Only used for reading old data; new
     * assignments always use the canonical code from reviewerDepartmentCodes().
     */
    private const REVIEW_DEPARTMENT_ALIASES = ['PRD' => 'PROD'];

    /**
     * Normalizes a stored trials_review.department value for comparison
     * against reviewerDepartmentCodes()/reviewDepartmentsForUser(), applying
     * REVIEW_DEPARTMENT_ALIASES so historical rows using a since-renamed code
     * still match.
     */
    public static function normalizeReviewDepartment(?string $dept): string
    {
        $code = self::normalizeDepartment($dept);

        return self::REVIEW_DEPARTMENT_ALIASES[$code] ?? $code;
    }

    /**
     * Expands a list of canonical review-department codes to also include
     * any legacy alias that maps onto one of them — for building a SQL
     * IN(...) list that must match both old and new stored values.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public static function expandReviewDepartmentAliases(array $codes): array
    {
        $expanded = $codes;
        foreach (self::REVIEW_DEPARTMENT_ALIASES as $old => $new) {
            if (in_array($new, $codes, true) && ! in_array($old, $expanded, true)) {
                $expanded[] = $old;
            }
        }

        return $expanded;
    }

    public function reviewUnitCode(): string
    {
        return self::normalizeDepartment($this->review_unit ?? '');
    }

    /**
     * Phase 1 of the RBAC/Team-master redesign (see memory
     * rbac_team_lane_redesign_2026_09_22): `review_team_id` points at a
     * `master_options` row (type=reviewer_department) — the same shared
     * table the legacy app and the Access Rights "Reviewer Department
     * Master" panel already read/write. No DB-level FK (matches this app's
     * established no-FK-on-shared-tables convention).
     *
     * @return BelongsTo<MasterOption, $this>
     */
    public function reviewTeam(): BelongsTo
    {
        return $this->belongsTo(MasterOption::class, 'review_team_id');
    }

    /**
     * Active review teams (`master_options`, type=reviewer_department) as
     * id+name+sort_order rows, for id-based pickers (Access Rights' Review
     * Team select, and any future Lane Configuration picker per Phase 3 of
     * the RBAC/Team-master redesign) — as opposed to reviewerDepartmentCodes(),
     * which only returns the normalized name strings.
     *
     * @return Collection<int, array{id: int, name: string, sort_order: int}>
     */
    public static function reviewTeams(): Collection
    {
        return MasterOption::query()
            ->where('type', 'reviewer_department')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order'])
            ->map(fn (MasterOption $option) => [
                'id' => $option->id,
                'name' => $option->name,
                'sort_order' => $option->sort_order,
            ]);
    }

    /**
     * The hardcoded default review-team codes, i.e. what's available even
     * with zero `master_options` (type=reviewer_department) rows. Exposed
     * separately from reviewerDepartmentCodes() so the Access Rights screen
     * can display these as read-only "built-in" entries alongside whatever
     * custom departments have actually been added.
     *
     * @return list<string>
     */
    public static function defaultReviewerDepartmentCodes(): array
    {
        return ['PROD', 'RNI', 'QAC', 'PRNI', 'PI'];
    }

    /**
     * Department codes eligible to be a per-department reviewer, i.e. the
     * hardcoded defaults plus anything added via master_options
     * (type=reviewer_department). Falls back to defaults if that table
     * can't be queried (matches legacy bootstrap.php behavior).
     *
     * @return list<string>
     */
    public static function reviewerDepartmentCodes(): array
    {
        $defaults = self::defaultReviewerDepartmentCodes();

        try {
            $codes = $defaults;
            $names = MasterOption::query()
                ->where('type', 'reviewer_department')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name');

            foreach ($names as $name) {
                $code = self::normalizeDepartment($name);
                if ($code !== '' && ! in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }

            return $codes;
        } catch (\Throwable) {
            return $defaults;
        }
    }

    /**
     * Review-team codes this user is a reviewer for. Prefers the ID-backed
     * `review_team_id` (Phase 1 of the RBAC/Team-master redesign — see
     * memory rbac_team_lane_redesign_2026_09_22) when set, resolving it to
     * its `master_options` row's name; falls back to the legacy free-text
     * `review_unit` column when `review_team_id` is null, so users not yet
     * backfilled/migrated to the new column still work. Same return shape
     * (list of team name strings) as before this phase, so every existing
     * call site (Rule::in() validations, TrialReviewPolicy,
     * Trial::reviewStatusByDepartment(), the manage-line-configuration-report
     * Gate, etc.) keeps working unmodified.
     *
     * @return list<string>
     */
    public function reviewDepartmentsForUser(): array
    {
        $codes = self::reviewerDepartmentCodes();

        if ($this->review_team_id !== null) {
            $name = MasterOption::query()
                ->where('id', $this->review_team_id)
                ->where('type', 'reviewer_department')
                ->value('name');

            if ($name !== null) {
                $code = self::normalizeDepartment($name);

                return in_array($code, $codes, true) ? [$code] : [];
            }
        }

        $unit = $this->reviewUnitCode();

        return in_array($unit, $codes, true) ? [$unit] : [];
    }

    /**
     * Port of legacy's is_reviewer() (!is_manager_qac() && ...) — the
     * exclusion is deliberately kept scoped to the exact legacy role string
     * 'Manager QAC', not the broader 'Manager' tier it now maps onto via
     * effectiveRole(): legacy only excludes this one role from also
     * counting as a per-department reviewer (its department is already
     * implied by the role name itself), while a Team Leader/Part Leader
     * with a review_unit set has always still counted as a reviewer.
     */
    public function isReviewer(): bool
    {
        return $this->role !== 'Manager QAC' && count($this->reviewDepartmentsForUser()) > 0;
    }

    /**
     * Assignable role categories: the hardcoded defaults, plus any custom
     * roles added via master_options (type=role_category). Port of legacy
     * bootstrap.php's role_categories() — unlike legacy, this deliberately
     * does NOT fold reviewerDepartmentCodes() in here: which review team a
     * user belongs to is managed independently via `review_unit` (Access
     * Rights' "Review Team" field), not by picking a department code as
     * someone's Role. See the 2026-09-14 `review_unit` migration doc comment.
     *
     * @return list<string>
     */
    public static function roleCategories(): array
    {
        // Trimmed 2026-09-22 (Phase 2 of the RBAC/Team-master redesign) from
        // ['Staff','Viewer','Manager QAC','Admin','Super Admin'] to generic,
        // department-free tiers — 'Manager QAC' baked a department into the
        // role name; which team a Manager belongs to is now handled purely
        // via review_team_id (Access Rights' "Review Team" field) instead.
        $roles = ['Staff', 'Viewer', 'Manager', 'Admin', 'Super Admin'];

        try {
            $names = MasterOption::query()
                ->where('type', 'role_category')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name');

            foreach ($names as $name) {
                $name = trim((string) $name);
                if ($name !== '' && ! in_array($name, $roles, true)) {
                    $roles[] = $name;
                }
            }
        } catch (\Throwable) {
            // fall through with defaults, matching legacy behavior
        }

        return $roles;
    }

    public function roleLabel(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Admin';
        }
        if ($this->isAdmin()) {
            return 'Admin';
        }
        if ($this->effectiveRole() === 'Staff') {
            return 'Staff Trial';
        }
        if ($this->isViewer()) {
            return 'Viewer';
        }
        if ($this->isReviewer()) {
            return 'Reviewer';
        }

        // No explicit 'Manager QAC'/'Manager' branch needed here: a
        // Manager-tier user without a review department falls through to
        // the raw `role` column below, which already reads e.g. 'Manager
        // QAC' or 'Team Leader' for a not-yet-migrated legacy account, or
        // the effective 'Manager' for one whose role was set via this app.
        return $this->role;
    }

    public function isTrialOwner(Trial $trial): bool
    {
        return trim((string) $trial->created_by) !== ''
            && strcasecmp(trim((string) $trial->created_by), trim((string) $this->email)) === 0;
    }

    public function hasTrialEditPermission(int $trialId): bool
    {
        if (! $trialId) {
            return false;
        }

        return TrialEditPermission::query()
            ->where('trial_id', $trialId)
            ->where('user_id', $this->id)
            ->where('can_edit', 1)
            ->whereNull('revoked_at')
            ->exists();
    }

    public function hasAssignedApproval(): bool
    {
        return Trial::query()
            ->where('progress_status', 'Ready for Approval')
            ->whereNull('deleted_at')
            ->where('approver_user_id', $this->id)
            ->exists();
    }

    /**
     * Whether this user can approve trials in general (used to gate the
     * approval queue / list scoping) — as opposed to Policy::approve(),
     * which checks a specific trial.
     */
    public function canApproveTrials(): bool
    {
        return $this->isAdmin()
            || in_array($this->effectiveRole(), self::approverEligibleRoles(), true)
            || $this->hasAssignedApproval();
    }
}
