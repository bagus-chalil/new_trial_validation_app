<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standardizes the "PI" review team to "PIE" (the name the business
     * actually uses, and what Line Configuration already calls it). Only
     * this app's own columns are touched: master_options (reviewer_department),
     * users.review_unit and users.review_team_id. Historical
     * trials_review.department = 'PI' rows (shared with legacy) are left
     * as-is — User::REVIEW_DEPARTMENT_ALIASES maps them onto PIE when read.
     * Idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('master_options')) {
            return;
        }

        $pi = DB::table('master_options')
            ->where('type', 'reviewer_department')
            ->whereRaw('UPPER(TRIM(name)) = ?', ['PI'])
            ->first();
        $pie = DB::table('master_options')
            ->where('type', 'reviewer_department')
            ->whereRaw('UPPER(TRIM(name)) = ?', ['PIE'])
            ->first();

        $targetId = $pie?->id;

        if ($pi !== null && $pie === null) {
            DB::table('master_options')->where('id', $pi->id)->update(['name' => 'PIE']);
            $targetId = $pi->id;
        } elseif ($pi !== null && $pie !== null) {
            DB::table('master_options')->where('id', $pi->id)->update(['is_active' => 0]);
        }

        if (Schema::hasColumn('users', 'review_unit')) {
            DB::table('users')->whereRaw('UPPER(TRIM(review_unit)) = ?', ['PI'])->update(['review_unit' => 'PIE']);
        }

        if ($pi !== null && $targetId !== null && Schema::hasColumn('users', 'review_team_id')) {
            DB::table('users')->where('review_team_id', $pi->id)->update(['review_team_id' => $targetId]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: PI is no longer a valid code.
    }
};
