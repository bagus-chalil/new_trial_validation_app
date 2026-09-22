<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 of the RBAC/Team-master redesign (see
     * memory rbac_team_lane_redesign_2026_09_22 / the approved plan at
     * ~/.claude/plans/cheerful-twirling-lampson.md). Per the simplification
     * discovered before implementation started, this does NOT create a new
     * `review_teams` table — it reuses the existing shared `master_options`
     * table (`type='reviewer_department'`) as the Team master directly, the
     * same rows the "Reviewer Department Master" panel on Access Rights and
     * the legacy PHP app (`../app/bootstrap.php`) already read/write. This
     * column lets a user's review team be referenced by a stable id instead
     * of the free-text `review_unit` string, so renaming a team in
     * `master_options` no longer risks orphaning existing assignments.
     *
     * No DB-level FK to `master_options.id` — matches this app's established
     * no-FK-on-shared-tables convention (e.g. `trial_line_configuration_reports`).
     * `review_unit` is left untouched (not dropped); AccessRightController
     * keeps both columns in sync going forward so any code still reading the
     * old string column keeps working during the transition.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'review_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('review_team_id')->nullable()->after('review_unit');
            });
        }

        // Ensure the 5 hardcoded default reviewer-department codes exist as
        // real `master_options` rows before resolving any user's FK against
        // them — they currently only exist as a hardcoded array merged in at
        // read time (User::reviewerDepartmentCodes()), not necessarily as
        // real rows. Idempotent (matched case-insensitively), safe to re-run.
        foreach (User::defaultReviewerDepartmentCodes() as $code) {
            $exists = DB::table('master_options')
                ->where('type', 'reviewer_department')
                ->whereRaw('UPPER(TRIM(name)) = ?', [$code])
                ->exists();

            if (! $exists) {
                DB::table('master_options')->insert([
                    'type' => 'reviewer_department',
                    'name' => $code,
                    'sort_order' => 0,
                    'is_active' => true,
                ]);
            }
        }

        // One-time backfill: for every user with a non-null `review_unit`,
        // resolve it (alias-aware via User::normalizeReviewDepartment(), so a
        // legacy 'PRD' row resolves to the 'PROD' master_options row, and
        // case-insensitively via User::normalizeDepartment()) to the matching
        // master_options row and set review_team_id. Only touches rows still
        // missing review_team_id, so this is safe to re-run.
        DB::table('users')
            ->whereNotNull('review_unit')
            ->whereNull('review_team_id')
            ->orderBy('id')
            ->select(['id', 'review_unit'])
            ->cursor()
            ->each(function (object $user): void {
                $code = User::normalizeReviewDepartment($user->review_unit);
                if ($code === '') {
                    return;
                }

                $optionId = DB::table('master_options')
                    ->where('type', 'reviewer_department')
                    ->whereRaw('UPPER(TRIM(name)) = ?', [$code])
                    ->value('id');

                if ($optionId === null) {
                    // A custom department that isn't one of the 5 defaults
                    // and has no matching master_options row (shouldn't
                    // normally happen — reviewerDepartmentCodes() only ever
                    // offers names actually present in master_options) —
                    // create it so the FK still resolves rather than
                    // silently leaving this user's review_team_id null.
                    $optionId = DB::table('master_options')->insertGetId([
                        'type' => 'reviewer_department',
                        'name' => $code,
                        'sort_order' => 0,
                        'is_active' => true,
                    ]);
                }

                DB::table('users')->where('id', $user->id)->update(['review_team_id' => $optionId]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'review_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('review_team_id');
            });
        }
    }
};
