<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipc_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products');
            $table->string('no_batch');
            $table->foreignId('master_line_id')->constrained('master_lines');
            $table->foreignId('created_by')->constrained('users');
            $table->string('current_stage')->default('startup');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_batches');
    }
};
