<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('master_products')
            ->whereNotNull('bulk_code')
            ->where('bulk_code', '!=', '')
            ->orderBy('id')
            ->each(function ($product) {
                DB::table('master_product_bulk_codes')->insert([
                    'master_product_id' => $product->id,
                    'bulk_code' => $product->bulk_code,
                    'no_batch' => null,
                    'is_active' => $product->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn('bulk_code');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('bulk_code')->nullable()->after('product_name');
        });
    }
};
