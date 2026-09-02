<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filling_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->string('sample_bulk_odor_status')->nullable();
            $table->string('sample_leakage_test_status')->nullable();
            $table->string('color')->nullable();
            $table->string('line_leader_name')->nullable();
            $table->text('remarks')->nullable();
            $table->string('decision')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filling_checks');
    }
};
