<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_product_bulk_codes', function (Blueprint $table) {
            $table->dropColumn('no_batch');
        });
    }

    public function down(): void
    {
        Schema::table('master_product_bulk_codes', function (Blueprint $table) {
            $table->string('no_batch')->nullable()->after('bulk_code');
        });
    }
};
