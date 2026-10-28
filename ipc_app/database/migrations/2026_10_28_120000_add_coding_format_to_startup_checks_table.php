<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            // Free text per tier; the literal string "N/A" means the tier has no coding (same
            // convention as `heating`).
            $table->text('coding_format_primary')->nullable()->after('heating');
            $table->text('coding_format_secondary')->nullable()->after('coding_format_primary');
            $table->text('coding_format_tersier')->nullable()->after('coding_format_secondary');
            // "Operator (Actual Coding)" is one photo per tier (ipc_attachments, fields
            // coding_primary/secondary/tersier); these flags mark a tier N/A instead.
            $table->boolean('coding_operator_na_primary')->default(false)->after('coding_format_tersier');
            $table->boolean('coding_operator_na_secondary')->default(false)->after('coding_operator_na_primary');
            $table->boolean('coding_operator_na_tersier')->default(false)->after('coding_operator_na_secondary');
        });
    }

    public function down(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            $table->dropColumn(['coding_format_primary', 'coding_format_secondary', 'coding_format_tersier', 'coding_operator_na_primary', 'coding_operator_na_secondary', 'coding_operator_na_tersier']);
        });
    }
};
