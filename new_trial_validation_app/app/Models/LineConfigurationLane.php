<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 of the RBAC/Team-master redesign (see memory
 * rbac_team_lane_redesign_2026_09_22): one row per Line Configuration Report
 * sign-off stage ('approved_pie'/'checked_prod', exactly 2 fixed rows — see
 * the migration), each naming an editable display `label` and an optional
 * `required_team_id` (a `master_options` type=reviewer_department row — the
 * same Team master Phase 1 built). `required_team_id=null` means "any active
 * user is eligible" (today's actual Approved(PIE) behavior).
 *
 * `userEligibleForStage()`/`eligibleUsersQuery()`/`constrainToStage()` are
 * the single source of truth for "who may be assigned to / act as this
 * stage" — used by the `manage-line-configuration-report` Gate,
 * SaveTrialLineConfigurationReportRequest's assignment validation, and
 * TrialReportController's approver-picker lists, so the rule only ever lives
 * in one place instead of three hardcoded 'PROD' literals.
 *
 * @property int $id
 * @property string $stage_key
 * @property string $label
 * @property int|null $required_team_id
 */
#[Fillable(['stage_key', 'label', 'required_team_id'])]
class LineConfigurationLane extends Model
{
    protected $table = 'line_configuration_lanes';

    /**
     * @return BelongsTo<MasterOption, $this>
     */
    public function requiredTeam(): BelongsTo
    {
        return $this->belongsTo(MasterOption::class, 'required_team_id');
    }

    public static function requiredTeamId(string $stageKey): ?int
    {
        return static::query()->where('stage_key', $stageKey)->value('required_team_id');
    }

    /**
     * The lane's current, editable label — falls back to $default if no row
     * exists yet (shouldn't normally happen, the migration always seeds
     * both, but keeps every call site safe regardless).
     */
    public static function label(string $stageKey, string $default): string
    {
        return static::query()->where('stage_key', $stageKey)->value('label') ?? $default;
    }

    /**
     * @return array{approved_pie: string, checked_prod: string}
     */
    public static function labels(): array
    {
        return [
            'approved_pie' => static::label('approved_pie', 'Approved (PIE Leader)'),
            'checked_prod' => static::label('checked_prod', 'Acknowledged (PROD)'),
        ];
    }

    /**
     * True when $user may be assigned to / act as $stageKey — belongs to the
     * stage's required team (via User::reviewDepartmentsForUser(), so both
     * the ID-backed review_team_id and the legacy review_unit fallback are
     * honored, same as everywhere else in this app), or the stage has no
     * required team at all (open to any active user).
     */
    public static function userEligibleForStage(User $user, string $stageKey): bool
    {
        $teamId = static::requiredTeamId($stageKey);
        if ($teamId === null) {
            return true;
        }

        $teamName = MasterOption::query()->where('id', $teamId)->value('name');
        if ($teamName === null) {
            // Configured team no longer exists — fail open rather than
            // locking everyone out of a stage over stale config.
            return true;
        }

        return in_array(User::normalizeDepartment($teamName), $user->reviewDepartmentsForUser(), true);
    }

    /**
     * Constrains a query builder (Eloquent or plain query builder — both
     * expose where()/orWhere()/whereRaw() the same way, which is all this
     * uses) to users eligible for $stageKey. Used both for
     * Rule::exists()->where() closures (plain query builder) and for
     * building the approver-picker list (Eloquent).
     *
     * @param  EloquentBuilder<User>|QueryBuilder  $query
     * @return EloquentBuilder<User>|QueryBuilder
     */
    public static function constrainToStage(EloquentBuilder|QueryBuilder $query, string $stageKey): EloquentBuilder|QueryBuilder
    {
        $teamId = static::requiredTeamId($stageKey);
        if ($teamId === null) {
            return $query;
        }

        $teamName = MasterOption::query()->where('id', $teamId)->value('name');
        if ($teamName === null) {
            return $query;
        }

        $normalized = User::normalizeDepartment($teamName);

        return $query->where(function ($inner) use ($teamId, $normalized) {
            $inner->where('review_team_id', $teamId)
                ->orWhereRaw('UPPER(TRIM(review_unit)) = ?', [$normalized]);
        });
    }

    /**
     * Explicit users allowed to sign this stage (the "PIE Leader" list — the
     * PIE lane's *team* is the report makers, so leaders can't be a team).
     *
     * @return array<int, int>
     */
    public static function signerUserIds(string $stageKey): array
    {
        $laneId = static::query()->where('stage_key', $stageKey)->value('id');
        if ($laneId === null) {
            return [];
        }

        return DB::table('line_configuration_lane_users')
            ->where('lane_id', $laneId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Who may sign (acknowledge / approve / return) a stage. No one is
     * assigned on the form: an explicit user list wins when configured, else
     * the lane's team. A lane with neither configured has NO signers on
     * purpose — unlike userEligibleForStage(), "open to everyone" would let
     * any account sign a report.
     */
    public static function userMaySign(User $user, string $stageKey): bool
    {
        $explicit = static::signerUserIds($stageKey);
        if ($explicit !== []) {
            return in_array((int) $user->id, $explicit, true);
        }

        if (static::requiredTeamId($stageKey) === null) {
            return false;
        }

        return static::userEligibleForStage($user, $stageKey);
    }

    /**
     * Active users who may sign $stageKey — same rule as userMaySign(), as a
     * query (e-mail / notification recipients, the form's "who signs" hint).
     *
     * @return EloquentBuilder<User>
     */
    public static function signersQuery(string $stageKey): EloquentBuilder
    {
        $query = User::query()->where('is_active', 1)->whereNull('deleted_at');
        $explicit = static::signerUserIds($stageKey);

        if ($explicit !== []) {
            return $query->whereIn('id', $explicit);
        }

        if (static::requiredTeamId($stageKey) === null) {
            return $query->whereRaw('1 = 0');
        }

        /** @var EloquentBuilder<User> */
        return static::constrainToStage($query, $stageKey);
    }
}
