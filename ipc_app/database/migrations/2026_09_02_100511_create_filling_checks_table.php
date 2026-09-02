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

            $table->string('sample_bulk_odor_status')->nullable(); // Conform / Not Conform
            $table->string('sample_leakage_test_status')->nullable(); // Conform / Not Conform
            $table->string('standard_weight_and_volume')->nullable(); // real STANDAR_WEIGHT&VOLUME column, was missing entirely
            $table->decimal('average_weight', 10, 4)->nullable(); // computed from weight samples 1-10, real AVERAGE_WEIGHT column
            $table->string('color')->nullable();
            $table->string('line_leader_name')->nullable();
            $table->text('remarks')->nullable(); // required on this screen in legacy, unlike Startup Check
            $table->string('decision')->nullable(); // Passed / Hold / Reject

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filling_checks');
    }
};
