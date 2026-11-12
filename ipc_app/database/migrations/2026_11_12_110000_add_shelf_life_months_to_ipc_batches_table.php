<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shelf-life (months) used for THIS batch: filled when the batch is created (prefilled from the
// product's Master Produk value, required), editable on Startup Check, and the basis of
// EXP date = mixing date + shelf-life. A snapshot per batch, like bulk_code. Additive.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->unsignedSmallInteger('shelf_life_months')->nullable()->after('exp_date');
            // 'tube' | 'non_tube', chosen on Startup Check: decides the coding AI path instead of
            // letting the AI guess the packaging from the photo.
            $table->string('primary_packaging_type', 20)->nullable()->after('shelf_life_months');
        });
    }

    public function down(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_months', 'primary_packaging_type']);
        });
    }
};
