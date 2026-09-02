<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipc_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->constrained('ipc_batches')->cascadeOnDelete();
            $table->string('stage');
            $table->foreignId('printed_by_user_id')->constrained('users');
            $table->timestamp('printed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_print_logs');
    }
};
