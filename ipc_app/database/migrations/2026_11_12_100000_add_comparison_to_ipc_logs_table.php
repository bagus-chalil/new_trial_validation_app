<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Startup AI cross-check: one ipc_logs row can compare several values (batch, mixing date, EXP,
// shelf-life...) against what the operator typed / the batch master data. `comparison` keeps
// the per-item result (label, expected, read, verdict) plus the values the operator had typed
// at scan time, so the advisory phase can be measured later without re-running anything.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_logs', function (Blueprint $table) {
            $table->json('comparison')->nullable()->after('computed_value');
        });
    }

    public function down(): void
    {
        Schema::table('ipc_logs', function (Blueprint $table) {
            $table->dropColumn('comparison');
        });
    }
};
