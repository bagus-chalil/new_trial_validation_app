<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 of the RBAC/Team-master redesign (see memory
     * rbac_team_lane_redesign_2026_09_22). The *current* (is_locked=false)
     * report always renders the live `line_configuration_lanes` label, but a
     * row that gets locked (ReturnTrialLineConfigurationReport) must keep
     * reading exactly the label it had at that point even if an admin later
     * renames the lane — these two columns are that one-time snapshot,
     * stamped by SaveTrialLineConfigurationReport on every save of the
     * current row (see that action). Nullable so existing rows (saved before
     * this column existed) fall back to the hardcoded default label at
     * render time, same as every other additive column in this app.
     */
    public function up(): void
    {
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('trial_line_configuration_reports', 'approved_pie_label')) {
                $table->string('approved_pie_label', 100)->nullable()->after('approved_pie_user_id');
            }
            if (! Schema::hasColumn('trial_line_configuration_reports', 'checked_prod_label')) {
                $table->string('checked_prod_label', 100)->nullable()->after('checked_prod_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (Schema::hasColumn('trial_line_configuration_reports', 'approved_pie_label')) {
                $table->dropColumn('approved_pie_label');
            }
            if (Schema::hasColumn('trial_line_configuration_reports', 'checked_prod_label')) {
                $table->dropColumn('checked_prod_label');
            }
        });
    }
};
