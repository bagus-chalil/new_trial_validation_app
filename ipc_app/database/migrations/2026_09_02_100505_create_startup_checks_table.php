<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            // Checklist items — Available/Not Available or Conform/Not Conform, exact wording per
            // item not yet verified against the source PDF screenshots (see ipc_app/CLAUDE.md).
            $table->string('product_standard_status')->nullable();
            $table->string('sample_challenge_test_status')->nullable();
            $table->string('wi_im_match_status')->nullable();
            $table->string('pm_bom_match_status')->nullable();
            $table->string('bulk_status_status')->nullable();
            $table->string('machine_vision_status')->nullable();
            $table->string('machine_weigher_status')->nullable();
            $table->string('machine_roller_status')->nullable();
            $table->string('machine_load_cell_status')->nullable();
            $table->string('machine_balance_status')->nullable();
            $table->string('validation_report_status')->nullable();
            $table->string('identity_line_board_status')->nullable();

            $table->decimal('filling_range_min', 10, 2)->nullable();
            $table->decimal('filling_range_max', 10, 2)->nullable();
            $table->decimal('density', 10, 4)->nullable();
            $table->string('heating')->nullable();

            $table->string('line_leader_name')->nullable();
            $table->string('operator_name')->nullable();

            // Plain values for fields that also carry an attached photo (see ipc_attachments).
            $table->string('im_number')->nullable();
            $table->string('color')->nullable();
            $table->string('coding')->nullable();
            $table->string('temperature_setting')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_checks');
    }
};
