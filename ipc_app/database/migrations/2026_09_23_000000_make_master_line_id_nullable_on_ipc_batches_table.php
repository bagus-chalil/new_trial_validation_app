<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line is now chosen on the Startup Check form (together with Mixing Date, see the
 * 2026-09-22 migration) instead of at batch creation, so a freshly-created batch has
 * no line yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('master_line_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('master_line_id')->nullable(false)->change();
        });
    }
};
