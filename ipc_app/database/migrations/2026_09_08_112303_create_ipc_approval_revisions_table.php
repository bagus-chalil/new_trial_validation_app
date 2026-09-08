<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ipc_approval_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_approval_id')->constrained('ipc_approvals')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('decision');
            $table->foreignId('approver_user_id')->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['ipc_approval_id', 'revision_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ipc_approval_revisions');
    }
};
