<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_bulk_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products')->cascadeOnDelete();
            $table->string('bulk_code');
            $table->string('no_batch')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['master_product_id', 'bulk_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_bulk_codes');
    }
};
