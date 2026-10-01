<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            // Some products have no meaningful density — QC marks it N/A instead of leaving it
            // blank, so the report can tell "not applicable" apart from "forgot to fill in".
            $table->boolean('density_not_applicable')->default(false)->after('density');
        });
    }

    public function down(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            $table->dropColumn('density_not_applicable');
        });
    }
};
