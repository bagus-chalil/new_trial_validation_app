<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipc_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->constrained('ipc_batches')->cascadeOnDelete();
            $table->string('stage');
            $table->string('field_label');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_attachments');
    }
};
