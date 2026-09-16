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
        // Mirrors the 3 sign-off boxes on the source Excel sheet (row 59-62:
        // "Prepared(PIE)" / "Approved(PIE)" / "Checked(PROD)" / "Return(PROD)")
        // — "Prepared" is skipped since that's already the existing `pic`
        // field. Each toggle stores who flipped it and when (name/time
        // snapshot, not a user_id FK, matching this table's existing
        // no-FK convention) so the report preview can show real approval
        // status instead of just a blank checkbox.
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            $table->boolean('approved_pie')->default(false)->after('opinion');
            $table->string('approved_pie_by', 150)->nullable()->after('approved_pie');
            $table->timestamp('approved_pie_at')->nullable()->after('approved_pie_by');

            $table->boolean('checked_prod')->default(false)->after('approved_pie_at');
            $table->string('checked_prod_by', 150)->nullable()->after('checked_prod');
            $table->timestamp('checked_prod_at')->nullable()->after('checked_prod_by');

            $table->boolean('return_prod')->default(false)->after('checked_prod_at');
            $table->string('return_prod_by', 150)->nullable()->after('return_prod');
            $table->timestamp('return_prod_at')->nullable()->after('return_prod_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_line_configuration_reports', function (Blueprint $table) {
            $table->dropColumn([
                'approved_pie', 'approved_pie_by', 'approved_pie_at',
                'checked_prod', 'checked_prod_by', 'checked_prod_at',
                'return_prod', 'return_prod_by', 'return_prod_at',
            ]);
        });
    }
};
