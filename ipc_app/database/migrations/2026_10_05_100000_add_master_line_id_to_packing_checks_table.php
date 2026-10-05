<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packing can run on a different line than Filling (user, 2026-10-05), so Packing Check gets its
 * own line instead of always showing the batch's (Startup Check / Filling) line. Nullable: rows
 * saved before this fall back to the batch line wherever it's displayed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_checks', function (Blueprint $table) {
            $table->foreignId('master_line_id')->nullable()->after('user_id')->constrained('master_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packing_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_line_id');
        });
    }
};
