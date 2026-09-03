<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filling_checks', function (Blueprint $table) {
            // Real legacy TH_PROGESS column: counts every Save/Save & End click, since QC
            // revisits this screen repeatedly over hours to track quality drift, not just once.
            // Was entirely missing before — the port originally (wrongly) treated every save as
            // final. See the 2026-09-03 user feedback session for full context.
            $table->unsignedInteger('save_count')->default(0)->after('decision');

            // Superseded by displaying Startup Check's filling_range_min/max directly (already
            // captured a stage earlier) instead of asking QC to retype it here — same class of
            // fix as this table's earlier `color` column removal.
            $table->dropColumn('standard_weight_and_volume');
        });
    }

    public function down(): void
    {
        Schema::table('filling_checks', function (Blueprint $table) {
            $table->dropColumn('save_count');
            $table->string('standard_weight_and_volume')->nullable();
        });
    }
};
