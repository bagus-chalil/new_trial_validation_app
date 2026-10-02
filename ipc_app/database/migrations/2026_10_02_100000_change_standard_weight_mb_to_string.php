<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Std Bruto MB is written by QC as a range, e.g. "1920-2000" (user, 2026-10-02), not one number.
 * Existing decimal values are kept, minus the trailing zeros the decimal(10,4) column padded on.
 */
return new class extends Migration
{
    private const TABLES = ['packing_checks', 'packing_check_revisions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('standard_weight_mb', 50)->nullable()->change();
            });

            DB::table($table)->whereNotNull('standard_weight_mb')->orderBy('id')->each(function ($row) use ($table) {
                if (str_contains($row->standard_weight_mb, '.')) {
                    DB::table($table)->where('id', $row->id)
                        ->update(['standard_weight_mb' => rtrim(rtrim($row->standard_weight_mb, '0'), '.')]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            // A range can't go back into a decimal column.
            DB::table($table)->where('standard_weight_mb', 'like', '%-%')->update(['standard_weight_mb' => null]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('standard_weight_mb', 10, 4)->nullable()->change();
            });
        }
    }
};
