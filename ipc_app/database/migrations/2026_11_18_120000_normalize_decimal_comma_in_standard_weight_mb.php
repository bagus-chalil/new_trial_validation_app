<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data only, no schema change. Std Bruto MB is free text ("1920-2000"), and until 2026-11-18 a
 * decimal comma ("1920,5") was accepted and stored as typed. Stored numbers must always use a dot
 * (clean data, also for later AI training), so rewrite any leftover comma to a dot.
 *
 * Idempotent: only rows that still contain a comma are touched. Lossless (the comma was the decimal
 * separator, never a list separator, in this column), so down() has nothing to restore.
 * masterbox / no_pallet_qty are deliberately NOT touched: their comma is a list separator.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['packing_checks', 'packing_check_revisions'] as $table) {
            DB::table($table)
                ->where('standard_weight_mb', 'like', '%,%')
                ->update(['standard_weight_mb' => DB::raw("REPLACE(REPLACE(standard_weight_mb, ' ', ''), ',', '.')")]);
        }
    }

    public function down(): void
    {
        // Intentionally empty: "1920.5" -> "1920,5" would re-introduce the inconsistency.
    }
};
