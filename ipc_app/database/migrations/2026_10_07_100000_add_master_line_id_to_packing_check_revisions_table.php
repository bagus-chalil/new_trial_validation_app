<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packing line can change on any TH_PROGRESS round, so each round snapshots its own line for the
 * audit trail (user, 2026-10-07). Rounds saved before this stay NULL — the line they ran on was
 * never recorded, so it isn't guessed; reports fall back to the Packing Check's current line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_check_revisions', function (Blueprint $table) {
            $table->foreignId('master_line_id')->nullable()->after('user_id')->constrained('master_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packing_check_revisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_line_id');
        });
    }
};
