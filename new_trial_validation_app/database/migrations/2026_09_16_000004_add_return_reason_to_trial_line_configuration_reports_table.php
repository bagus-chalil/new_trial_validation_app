<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 2026-09-16, same-day follow-up: Return became its own dedicated,
        // auto-stamped action (ReturnTrialLineConfigurationReport) instead of
        // a freely-typed form field — see that action and
        // TrialLineConfigurationReportPolicy::returnReport() — and the user
        // asked for a mandatory reason (>=10 words) explaining why. Guarded
        // with hasColumn since a prior migration on this same table already
        // hit a partial-failure/re-run situation once today — see
        // 2026_09_16_000003's doc comment.
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('trial_line_configuration_reports', 'return_reason')) {
                $table->text('return_reason')->nullable()->after('return_prod_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (Schema::hasColumn('trial_line_configuration_reports', 'return_reason')) {
                $table->dropColumn('return_reason');
            }
        });
    }
};
