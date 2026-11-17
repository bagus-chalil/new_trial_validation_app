<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Startup AI accuracy measurement: one row per (batch, AI-read field), written when the Startup
// Check is saved. Pairs what the AI read (ai_value) with the value the operator finally saved
// (final_value). `source` says how the operator got there, so "Pakai saran" copies (which equal the
// AI by construction) are NOT counted as proof that the AI was right. Additive, never read by
// the workflow itself.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipc_ai_field_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('ipc_log_id')->nullable()->constrained('ipc_logs')->nullOnDelete();
            $table->string('field_key', 30); // mixing_date | no_batch | shelf_life | coding_batch | coding_exp | tube_exp
            $table->string('field_type', 30); // label_bulk | im_document | coding_print | tube_exp_llm
            $table->string('ai_value', 60)->nullable();
            $table->boolean('ai_trusted')->default(false);
            $table->string('final_value', 60)->nullable();
            $table->string('source', 30); // typed | suggestion_applied | edited_after_suggestion
            $table->string('outcome', 20); // match | mismatch | ai_unreadable
            $table->string('packaging_type', 20)->nullable();
            $table->timestamps();

            $table->unique(['ipc_batch_id', 'field_key'], 'ipc_ai_feedback_batch_field_unique');
            $table->index(['field_key', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_ai_field_feedback');
    }
};
