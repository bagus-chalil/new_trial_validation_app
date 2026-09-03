<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same class of fix as the earlier standard_weight_and_volume removal: the user pointed
        // out Line Leader is already captured once on Startup Check (startup_checks.line_leader_name)
        // for the same batch/shift — Filling Check now just displays that instead of re-asking.
        Schema::table('filling_checks', function (Blueprint $table) {
            $table->dropColumn('line_leader_name');
        });
    }

    public function down(): void
    {
        Schema::table('filling_checks', function (Blueprint $table) {
            $table->string('line_leader_name')->nullable();
        });
    }
};
