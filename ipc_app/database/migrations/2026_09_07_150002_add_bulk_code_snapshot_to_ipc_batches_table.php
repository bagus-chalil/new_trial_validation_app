<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->foreignId('master_product_bulk_code_id')->nullable()->after('master_product_id')->constrained('master_product_bulk_codes')->nullOnDelete();
            $table->string('bulk_code')->nullable()->after('no_batch');
        });
    }

    public function down(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_product_bulk_code_id');
            $table->dropColumn('bulk_code');
        });
    }
};
