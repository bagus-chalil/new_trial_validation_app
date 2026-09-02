<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipc_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->constrained('ipc_batches')->cascadeOnDelete();
            $table->string('stage');
            $table->string('decision')->nullable();
            $table->foreignId('approver_user_id')->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['ipc_batch_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_approvals');
    }
};
