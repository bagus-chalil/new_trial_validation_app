<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 of the RBAC/Team-master redesign (see memory
     * rbac_team_lane_redesign_2026_09_22 / the approved plan at
     * ~/.claude/plans/cheerful-twirling-lampson.md). Legacy
     * (../app/bootstrap.php) hardcodes literal checks against
     * `role==='Manager QAC'` and `role IN ('Team Leader','Part Leader',
     * 'Team Leader QA')` for its own approve/view/notification logic, so
     * this app can never rewrite the shared `role` column to the new
     * generic tiers without breaking legacy for those exact users. Instead
     * this adds a new, THIS-APP-OWNED `users.app_role` column — same
     * decoupling pattern already used for `review_unit`/`review_team_id`
     * on 2026-09-14/2026-09-22 — and backfills it once from `role` via a
     * pure mapping. Legacy never reads or writes this column.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'app_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('app_role', 50)->nullable()->after('role');
            });
        }

        $departmentCoupledRoles = [
            'Manager QAC' => 'QAC',
            'Team Leader' => null,
            'Part Leader' => null,
            'Team Leader QA' => null,
            'Team Leader Production' => null,
        ];

        DB::table('users')
            ->whereNull('app_role')
            ->orderBy('id')
            ->select(['id', 'role', 'review_team_id'])
            ->cursor()
            ->each(function (object $user) use ($departmentCoupledRoles): void {
                $role = trim((string) $user->role);

                if ($role === '') {
                    return;
                }

                if (array_key_exists($role, $departmentCoupledRoles)) {
                    DB::table('users')->where('id', $user->id)->update(['app_role' => 'Manager']);

                    $impliedTeamCode = $departmentCoupledRoles[$role];
                    if ($impliedTeamCode !== null && $user->review_team_id === null) {
                        $teamId = DB::table('master_options')
                            ->where('type', 'reviewer_department')
                            ->whereRaw('UPPER(TRIM(name)) = ?', [$impliedTeamCode])
                            ->value('id');

                        if ($teamId !== null) {
                            DB::table('users')->where('id', $user->id)->update([
                                'review_team_id' => $teamId,
                                'review_unit' => $impliedTeamCode,
                            ]);
                        }
                    }

                    return;
                }

                // 'Admin', 'Super Admin', 'Staff', 'Viewer' (unchanged 1:1)
                // and any custom role_category value: copied through as-is.
                DB::table('users')->where('id', $user->id)->update(['app_role' => $role]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'app_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('app_role');
            });
        }
    }
};
