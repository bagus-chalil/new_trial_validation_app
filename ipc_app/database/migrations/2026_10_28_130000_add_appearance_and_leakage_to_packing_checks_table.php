<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whole-form "Appearance" and "Uji Kebocoran" results (Conform / Not Conform / N/A) shown in
     * the summary block of the Packing report FR.QAC.182.03. Until now the report printed a
     * placeholder for both since no field captured them.
     */
    private const COLUMNS = ['appearance_status', 'leakage_test_status'];

    public function up(): void
    {
        foreach (['packing_checks', 'packing_check_revisions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                foreach (self::COLUMNS as $column) {
                    $table->string($column)->nullable()->after('tersier_shipper_label_status');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['packing_checks', 'packing_check_revisions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(self::COLUMNS);
            });
        }
    }
};
