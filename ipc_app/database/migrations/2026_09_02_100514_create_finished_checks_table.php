<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finished_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->string('wi_number')->nullable();
            $table->date('exp_date')->nullable();
            $table->decimal('quantity_wi', 12, 2)->nullable();
            $table->decimal('masterbox', 12, 2)->nullable();
            $table->decimal('no_pallet_qty', 12, 2)->nullable();
            $table->unsignedInteger('quantity_sampling_aql')->nullable();
            $table->unsignedInteger('quantity_special_inspection')->nullable();

            $table->string('disposition')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_checks');
    }
};
