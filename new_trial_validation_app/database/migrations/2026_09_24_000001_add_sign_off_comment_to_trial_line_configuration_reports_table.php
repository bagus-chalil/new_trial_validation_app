<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User request, 2026-09-24: the Approved(PIE)/Checked(PROD) one-click
     * confirmations (MarkTrialLineConfigurationReportSignOff) previously had
     * no way to leave a note — only Return had a (mandatory) reason field.
     * These are optional, freely-typed comments submitted alongside the same
     * Approve/Tandai Checked click, not a separate action.
     */
    public function up(): void
    {
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('trial_line_configuration_reports', 'approved_pie_comment')) {
                $table->text('approved_pie_comment')->nullable()->after('approved_pie_label');
            }
            if (! Schema::hasColumn('trial_line_configuration_reports', 'checked_prod_comment')) {
                $table->text('checked_prod_comment')->nullable()->after('checked_prod_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (Schema::hasColumn('trial_line_configuration_reports', 'approved_pie_comment')) {
                $table->dropColumn('approved_pie_comment');
            }
            if (Schema::hasColumn('trial_line_configuration_reports', 'checked_prod_comment')) {
                $table->dropColumn('checked_prod_comment');
            }
        });
    }
};
