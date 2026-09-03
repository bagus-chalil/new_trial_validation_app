<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable audit trail: every Save/Save & End writes a new row here (never updated,
        // never overwritten) alongside the "current state" update on filling_checks itself, so
        // an earlier save's remarks/decision/samples survive later saves — needed for reporting
        // on how QC's assessment evolved across TH_PROGESS revisions, per the user's explicit
        // 2026-09-03 request after the repeatable-save feature above.
        Schema::create('filling_check_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filling_check_id')->constrained('filling_checks')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->boolean('finalize');
            $table->string('sample_bulk_odor_status')->nullable();
            $table->string('sample_leakage_test_status')->nullable();
            $table->text('remarks')->nullable();
            $table->string('decision')->nullable();
            $table->decimal('average_weight', 10, 4)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['filling_check_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filling_check_revisions');
    }
};
