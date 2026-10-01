<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aligns the Packing checklist with the paper form FR.QAC.193.00 Rev 0 ("Laporan Pemeriksaan
     * In Process Control Filling & Packing", user-shared 2026-10-01), which splits items the
     * Power Apps port had merged: Capping/Sealing vs Coding Batch & EXP on Primary, and Coding
     * Batch & EXP vs Coding NA on Secondary/Tersier, plus Tersier's Shipper Label. Also adds the
     * header's "Data Timbang: Ada / Tidak Ada" field (captured once, like coding_machine).
     */
    private const CHECKLIST_COLUMNS = [
        'primary_capping_sealing_status' => 'primary_capping_batch_exp_status',
        'secondary_coding_batch_exp_status' => 'secondary_appearance_status',
        'tersier_coding_batch_exp_status' => 'tersier_appearance_status',
        'tersier_shipper_label_status' => 'tersier_coding_na_status',
    ];

    public function up(): void
    {
        foreach (['packing_checks', 'packing_check_revisions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                foreach (self::CHECKLIST_COLUMNS as $column => $after) {
                    $table->string($column)->nullable()->after($after);
                }
            });
        }

        Schema::table('packing_checks', function (Blueprint $table) {
            $table->string('weighing_data')->nullable()->after('coding_machine');
        });
    }

    public function down(): void
    {
        Schema::table('packing_checks', function (Blueprint $table) {
            $table->dropColumn('weighing_data');
        });

        foreach (['packing_checks', 'packing_check_revisions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(array_keys(self::CHECKLIST_COLUMNS));
            });
        }
    }
};
