<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->string('primary_appearance_status')->nullable();
            $table->string('primary_coding_status')->nullable();
            $table->string('primary_attribute_status')->nullable();

            $table->string('secondary_appearance_status')->nullable();
            $table->string('secondary_coding_status')->nullable();
            $table->string('secondary_attribute_status')->nullable();

            $table->string('tersier_appearance_status')->nullable();
            $table->string('tersier_coding_status')->nullable();
            $table->string('tersier_attribute_status')->nullable();

            $table->string('coding_machine')->nullable();
            $table->text('remarks')->nullable();
            $table->string('decision')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_checks');
    }
};
