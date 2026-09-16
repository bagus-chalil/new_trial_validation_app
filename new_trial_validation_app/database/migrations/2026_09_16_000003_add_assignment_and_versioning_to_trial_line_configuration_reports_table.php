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
        // 2026-09-16 follow-up: the person filling this report now assigns a
        // specific user to Approved(PIE)/Checked(PROD) (who gets emailed and
        // must click their own Approve/Checked action — see
        // MarkTrialLineConfigurationReportSignOff), and the report becomes
        // versioned once it's been Returned and edited again, so the
        // returned state stays in the audit trail instead of being
        // overwritten. No FK declared on the two *_user_id columns, matching
        // this table's own established no-FK convention (see the original
        // migration's doc comment). Every step below is guarded
        // (hasColumn/hasIndex) so this migration is safe to re-run after a
        // partial failure — MySQL DDL isn't transactional, so an earlier
        // attempt against the shared DB can leave columns added but the
        // index change not yet applied.
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('trial_line_configuration_reports', 'approved_pie_user_id')) {
                $table->unsignedInteger('approved_pie_user_id')->nullable()->after('approved_pie_at');
            }
            if (! Schema::hasColumn('trial_line_configuration_reports', 'checked_prod_user_id')) {
                $table->unsignedInteger('checked_prod_user_id')->nullable()->after('checked_prod_at');
            }
            if (! Schema::hasColumn('trial_line_configuration_reports', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('updated_by_user_id');
            }
            if (! Schema::hasColumn('trial_line_configuration_reports', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('version');
            }
            if (! Schema::hasColumn('trial_line_configuration_reports', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('is_locked');
            }
        });

        // The original migration declared trial_id as unique, but on the
        // real shared DB that constraint turned out not to actually exist
        // (confirmed via SHOW INDEX — likely because the table already
        // existed, from earlier live-verification, by the time that
        // migration ran, so its Schema::create() guard skipped creation
        // entirely) — so dropping it unconditionally fails with "check that
        // column/key exists". Only drop it if it's really there.
        if (Schema::hasIndex('trial_line_configuration_reports', ['trial_id'], 'unique')) {
            Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
                $table->dropUnique(['trial_id']);
            });
        }

        if (! Schema::hasIndex('trial_line_configuration_reports', ['trial_id', 'version'], 'unique')) {
            Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
                $table->unique(['trial_id', 'version']);
            });
        }

        if (! Schema::hasIndex('trial_line_configuration_reports', ['trial_id', 'is_locked'])) {
            Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
                $table->index(['trial_id', 'is_locked']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            if (Schema::hasIndex('trial_line_configuration_reports', ['trial_id', 'is_locked'])) {
                $table->dropIndex(['trial_id', 'is_locked']);
            }
            if (Schema::hasIndex('trial_line_configuration_reports', ['trial_id', 'version'], 'unique')) {
                $table->dropUnique(['trial_id', 'version']);
            }
            if (! Schema::hasIndex('trial_line_configuration_reports', ['trial_id'], 'unique')) {
                $table->unique('trial_id');
            }

            $table->dropColumn([
                'approved_pie_user_id', 'checked_prod_user_id',
                'version', 'is_locked', 'locked_at',
            ]);
        });
    }
};
