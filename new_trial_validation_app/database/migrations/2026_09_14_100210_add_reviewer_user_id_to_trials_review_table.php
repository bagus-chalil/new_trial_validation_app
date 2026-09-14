<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
     */
    public function up(): void
    {
        if (! Schema::hasColumn('trials_review', 'reviewer_user_id')) {
            Schema::table('trials_review', function (Blueprint $table) {
                // users.id is a plain INT (auto_increment), not BIGINT — must
                // match exactly for the FK below to be creatable.
                $table->unsignedInteger('reviewer_user_id')->nullable()->after('department');
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
