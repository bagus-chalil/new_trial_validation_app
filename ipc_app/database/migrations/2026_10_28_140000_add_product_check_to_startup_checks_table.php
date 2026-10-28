<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            // "Pemeriksaan Berat Produk" block of the paper forms: Appearance and Uji Kebocoran
            // (Conform / Not Conform / N/A). Separate from the 30 PCS checklist items above.
            $table->string('product_appearance_status')->nullable()->after('coding_operator_na_tersier');
            $table->string('product_leakage_test_status')->nullable()->after('product_appearance_status');
        });
    }

    public function down(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            $table->dropColumn(['product_appearance_status', 'product_leakage_test_status']);
        });
    }
};
