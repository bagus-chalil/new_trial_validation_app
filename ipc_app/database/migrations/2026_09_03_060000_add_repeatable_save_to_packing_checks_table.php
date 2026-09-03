<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_checks', function (Blueprint $table) {
            // Real legacy TH_PROGRESS column on the Packing SharePoint list (confirmed in
            // References/DataSources.json — it was missed by the 2026-09-02 reconciliation pass,
            // which shipped this stage as a single all-or-nothing save). QC re-inspects the same
            // batch repeatedly over a shift, so every Simpan/Simpan & Selesaikan bumps this.
            $table->unsignedInteger('save_count')->default(0)->after('decision');
        });
    }

    public function down(): void
    {
        Schema::table('packing_checks', function (Blueprint $table) {
            $table->dropColumn('save_count');
        });
    }
};
