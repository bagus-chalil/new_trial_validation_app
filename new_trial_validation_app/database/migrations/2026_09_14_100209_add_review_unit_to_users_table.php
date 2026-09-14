<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy `department`/`role` on `users` are shared with the still-live
     * legacy PHP app and overload two concepts: a person's real
     * organizational division (e.g. "PRD" = Product Development) AND, for a
     * handful of users, which review team they belong to for trial
     * department-review purposes — legacy's reviewer_department_codes()
     * hardcodes PRD/RNI/QAC/PRNI/PI and matches a user's role OR department
     * against that list. This column decouples the review-team concept into
     * its own field, owned only by this app, so it can be renamed/managed
     * independently without touching the shared `department`/`role` columns
     * legacy still depends on. See User::reviewerDepartmentCodes()/
     * reviewDepartmentsForUser().
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'review_unit')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('review_unit', 50)->nullable()->after('department');
            });
        }

        // One-time backfill: any user who currently qualifies as a reviewer
        // under the legacy role-or-department match (PRD/RNI/QAC/PRNI/PI)
        // gets that same code copied into review_unit — except PRD, which
        // becomes PROD here (PRD is a real division name; PROD is the
        // decoupled review-team code going forward). Idempotent (only fills
        // rows that are still null), safe to re-run.
        $legacyCodes = ['PRD', 'RNI', 'QAC', 'PRNI', 'PI'];
        $alias = ['PRD' => 'PROD'];

        foreach ($legacyCodes as $code) {
            $reviewUnit = $alias[$code] ?? $code;

            DB::table('users')
                ->whereNull('review_unit')
                ->where(function ($q) use ($code) {
                    $q->whereRaw('UPPER(TRIM(role)) = ?', [$code])
                        ->orWhereRaw('UPPER(TRIM(department)) = ?', [$code]);
                })
                ->update(['review_unit' => $reviewUnit]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'review_unit')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('review_unit');
            });
        }
    }
};
