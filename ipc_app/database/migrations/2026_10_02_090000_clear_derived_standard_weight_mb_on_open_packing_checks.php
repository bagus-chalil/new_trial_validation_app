<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Std Bruto MB (standard_weight_mb) used to be auto-filled from Start Inspection's
 * weight-master-box samples on every save. It is now typed once by QC and locked after, so an
 * unfinished Packing Check still carrying the old derived value would show it as already locked.
 * Clear it on open checks only — finalized checks and revision snapshots keep their history.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('packing_checks')
            ->whereNull('completed_at')
            ->update(['standard_weight_mb' => null]);
    }

    public function down(): void
    {
        // Irreversible: the derived values aren't worth recomputing.
    }
};
