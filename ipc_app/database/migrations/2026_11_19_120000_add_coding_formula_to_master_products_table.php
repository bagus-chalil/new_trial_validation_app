<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-SKU coding formula for the Vision Service `coding_formula` field_type (Startup Primary
 * Coding read). Additive and nullable: NULL means "auto" (every catalog formula is tried), so no
 * admin has to fill it. Holds what the Vision Service accepts as-is: a catalog key, one template
 * string, or a JSON array of templates, e.g. ["{BATCH3}","EXP {MMM} {YY}"].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->text('coding_formula')->nullable()->after('shelf_life_months');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn('coding_formula');
        });
    }
};
