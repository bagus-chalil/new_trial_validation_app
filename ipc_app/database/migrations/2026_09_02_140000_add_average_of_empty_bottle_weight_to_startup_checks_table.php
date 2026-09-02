<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            // Real column found on the export's Start list (References/DataSources.json),
            // missing from the original schema pass. Written by legacy's Start_Check save
            // as the mean of the 30 startup_bottle_weights samples (Controls/832.json's
            // Label3 formula) and consumed by Filling_Check's per-sample weight_result
            // formula (Controls/625.json: (weight - AVERAGE_OF_EMPTY_BOTTLE_WEIGHT) / DENSITY).
            $table->decimal('average_of_empty_bottle_weight', 10, 4)->nullable()->after('density');
        });
    }

    public function down(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            $table->dropColumn('average_of_empty_bottle_weight');
        });
    }
};
