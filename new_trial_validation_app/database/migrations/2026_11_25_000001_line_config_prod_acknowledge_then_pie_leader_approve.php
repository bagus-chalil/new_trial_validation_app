<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Line Configuration Report sign-off order reversed (2026-11-25, user
     * request): PROD acknowledges first (any eligible PL/TL), then a PIE
     * Leader approves (Mr. DJ / M. Moon). Nobody is picked on the form any
     * more — any eligible user may sign, so:
     *
     *  - `line_configuration_lane_users`: optional explicit user list per
     *    lane (the PIE lane's team is the report *makers*, so the leaders who
     *    approve can't be expressed as a team). Empty list = fall back to the
     *    lane's team. Additive, new table.
     *  - `submitted_at`: what marks a report as "in the approval chain" now
     *    that no approver is assigned at submit time. Backfilled for reports
     *    that already have an assignee, so in-flight reports stay locked.
     *  - Lane labels are renamed only where they still hold the old seeded
     *    defaults (an admin-edited label is left alone).
     *
     * Column names approved_pie/checked_prod are kept on purpose: they are
     * snapshotted into locked historical versions, so renaming them would
     * rewrite history for no gain.
     */
    public function up(): void
    {
        if (! Schema::hasTable('line_configuration_lane_users')) {
            Schema::create('line_configuration_lane_users', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('lane_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['lane_id', 'user_id']);
            });
        }

        if (! Schema::hasColumn('trial_line_configuration_reports', 'submitted_at')) {
            Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
                $table->timestamp('submitted_at')->nullable();
            });

            DB::table('trial_line_configuration_reports')
                ->where(fn ($q) => $q->whereNotNull('approved_pie_user_id')->orWhereNotNull('checked_prod_user_id'))
                ->update(['submitted_at' => DB::raw('COALESCE(updated_at, created_at)')]);
        }

        DB::table('line_configuration_lanes')
            ->where('stage_key', 'checked_prod')->where('label', 'Checked (PROD)')
            ->update(['label' => 'Acknowledged (PROD)']);
        DB::table('line_configuration_lanes')
            ->where('stage_key', 'approved_pie')->where('label', 'Approved (PIE)')
            ->update(['label' => 'Approved (PIE Leader)']);
    }

    public function down(): void
    {
        Schema::dropIfExists('line_configuration_lane_users');

        if (Schema::hasColumn('trial_line_configuration_reports', 'submitted_at')) {
            Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
                $table->dropColumn('submitted_at');
            });
        }
    }
};
