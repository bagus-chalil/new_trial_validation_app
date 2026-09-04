<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finished_checks', function (Blueprint $table) {
            // Same TH_PROGESS-style save counter as filling_checks.save_count/packing_checks.save_count
            // — added after the fact once the user pointed out Finished Check was the only
            // save-capable stage with no "Riwayat Simpan" history, unlike its two siblings.
            $table->unsignedInteger('save_count')->default(0)->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('finished_checks', function (Blueprint $table) {
            $table->dropColumn('save_count');
        });
    }
};
