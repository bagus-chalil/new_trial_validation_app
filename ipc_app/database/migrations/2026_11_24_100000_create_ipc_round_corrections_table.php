<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for "Minta Revisi" at TH Progress level. One row per (stage, saved round) an
 * approver flagged, or per Startup reopen (revision_no NULL — Startup has no rounds). The
 * row carries who asked and why, and — once QC fixes it — who corrected, why, and the full
 * before/after snapshot, so a corrected round never silently replaces what was saved before.
 * Additive only (new table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipc_round_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->constrained('ipc_batches')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->unsignedSmallInteger('revision_no')->nullable();
            $table->string('status', 10)->default('open');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('request_note')->nullable();
            $table->timestamp('requested_at')->nullable();

            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamp('corrected_at')->nullable();

            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamps();

            $table->index(['ipc_batch_id', 'stage', 'status'], 'ipc_round_corr_batch_stage_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_round_corrections');
    }
};
