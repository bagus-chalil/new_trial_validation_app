<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assigns a `trials_review` row to one specific reviewer, instead of the
     * whole matching department being able to act on it (see
     * TrialReviewPolicy::update()). Nullable and additive — legacy has no
     * awareness of this column and simply won't populate/read it, so its own
     * department-wide review-save behavior for rows it creates is unaffected
     * (this app treats a null value as "no specific assignment, fall back to
     * department matching" for exactly that reason).
     *
     * users.id is a plain, SIGNED int(11) (no `unsigned` keyword) — an
     * earlier version of this migration declared reviewer_user_id
     * `unsignedInteger`, which MySQL 8 rejects as an incompatible FK type
     * (error 3780: signed vs unsigned, not just size, has to match exactly).
     * That broke the production deploy: the ADD COLUMN half of the ALTER
     * succeeded (MySQL DDL isn't transactional) but the ADD CONSTRAINT half
     * threw, so `migrate --force` exited non-zero without the migration ever
     * being recorded as run — leaving some environments with the column
     * already present, still UNSIGNED, and no FK. The `else` branch below
     * heals exactly that state instead of silently no-op'ing via the
     * `hasColumn` guard, and the FK is added in its own idempotent step so a
     * retry (or this fix, on next deploy) finishes the job either way.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('trials_review', 'reviewer_user_id')) {
            Schema::table('trials_review', function (Blueprint $table) {
                $table->integer('reviewer_user_id')->nullable()->after('department');
            });
            Schema::table('trials_review', function (Blueprint $table) {
                $table->foreign('reviewer_user_id')->references('id')->on('users')->nullOnDelete();
            });

            return;
        }

        // Column already existed — only reachable on a real MySQL DB carrying
        // the aftermath of an earlier failed run (see docblock above); the
        // sqlite test DB always creates it fresh via the branch above and
        // never reaches here, so the MySQL-only information_schema queries
        // below are safe.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $type = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trials_review' AND COLUMN_NAME = 'reviewer_user_id'"
        )->COLUMN_TYPE ?? '';

        if (str_contains($type, 'unsigned') || str_contains($type, 'bigint')) {
            DB::statement('ALTER TABLE trials_review MODIFY reviewer_user_id INT NULL');
        }

        $hasForeignKey = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trials_review'
             AND CONSTRAINT_NAME = 'trials_review_reviewer_user_id_foreign'"
        )->c > 0;

        if (! $hasForeignKey) {
            Schema::table('trials_review', function (Blueprint $table) {
                $table->foreign('reviewer_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trials_review', 'reviewer_user_id')) {
            Schema::table('trials_review', function (Blueprint $table) {
                $table->dropForeign(['reviewer_user_id']);
                $table->dropColumn('reviewer_user_id');
            });
        }
    }
};
