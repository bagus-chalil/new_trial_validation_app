<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real Start_Inspection screen has one input per sample under a single "VOLUME / WEIGHT"
 * section (confirmed 2026-09-03 against a live legacy screenshot from the user, who also asked
 * not to split it into two inputs) — not one Volume field and one Weight field. Corrects the
 * original 2026-09-02 guess. Safe to alter directly (no follow-up migration) since the table has
 * zero rows — the Start Inspection UI didn't exist until this same session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startup_inspection_samples', function (Blueprint $table) {
            $table->dropColumn(['volume', 'weight']);
            $table->decimal('volume_weight', 10, 4)->nullable()->after('sample_no');
        });
    }

    public function down(): void
    {
        Schema::table('startup_inspection_samples', function (Blueprint $table) {
            $table->dropColumn('volume_weight');
            $table->decimal('volume', 10, 4)->nullable();
            $table->decimal('weight', 10, 4)->nullable();
        });
    }
};
