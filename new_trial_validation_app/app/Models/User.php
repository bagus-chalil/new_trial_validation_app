<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

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
 * @property string|null $department
 * @property string|null $review_unit
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 */
#[Fillable(['name', 'email', 'role', 'department', 'review_unit'])]
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

    public function isSuperAdmin(): bool
    {
        return $this->role === 'Super Admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin' || $this->isSuperAdmin();
    }

    public function isStaff(): bool
    {
        return $this->role === 'Staff' || $this->isAdmin();
    }

    public function isManagerQac(): bool
    {
        return $this->role === 'Manager QAC';
    }

    public function isViewer(): bool
    {
        return $this->role === 'Viewer';
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
     * @return list<string>
     */
    public static function approverEligibleRoles(): array
    {
        return ['Admin', 'Super Admin', 'Manager QAC', 'Team Leader', 'Part Leader', 'Team Leader QA', 'Manager'];
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
     * Department codes eligible to be a per-department reviewer, i.e. the
     * hardcoded defaults plus anything added via master_options
     * (type=reviewer_department). Falls back to defaults if that table
     * can't be queried (matches legacy bootstrap.php behavior).
     *
     * @return list<string>
     */
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
     * Review-team codes this user is a reviewer for, sourced from the
     * decoupled `review_unit` column (not `role`/`department` — those stay
     * legacy-owned, see the 2026-09-14 migration doc comment).
     *
     * @return list<string>
     */
    public function reviewDepartmentsForUser(): array
    {
        $codes = self::reviewerDepartmentCodes();
        $unit = $this->reviewUnitCode();

        return in_array($unit, $codes, true) ? [$unit] : [];
    }

    public function isReviewer(): bool
    {
        return ! $this->isManagerQac() && count($this->reviewDepartmentsForUser()) > 0;
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
        $roles = ['Staff', 'Viewer', 'Manager QAC', 'Admin', 'Super Admin'];

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
        if ($this->role === 'Staff') {
            return 'Staff Trial';
        }
        if ($this->isViewer()) {
            return 'Viewer';
        }
        if ($this->isReviewer()) {
            return 'Reviewer';
        }
        if ($this->isManagerQac()) {
            return 'Manager QAC';
        }

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
        if ($this->isAdmin() || $this->isManagerQac()) {
            return true;
        }

        $approverRoles = ['Team Leader', 'Part Leader', 'Team Leader QA'];
        if (in_array($this->role, $approverRoles, true)) {
            return true;
        }

        return $this->hasAssignedApproval();
    }
}
