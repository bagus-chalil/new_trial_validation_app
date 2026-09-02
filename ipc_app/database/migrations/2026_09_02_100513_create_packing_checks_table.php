<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            // Real 13-item checklist confirmed against the Power Apps export 2026-09-02
            // (Controls/933.json) — deliberately asymmetric per tier, not a generic 3x3 grid.
            $table->string('primary_bulk_status')->nullable();
            $table->string('primary_packaging_status')->nullable();
            $table->string('primary_capping_batch_exp_status')->nullable();
            $table->string('primary_na_number_status')->nullable();
            $table->string('primary_attribute_status')->nullable();
            $table->string('primary_functional_test_status')->nullable();

            $table->string('secondary_identity_status')->nullable();
            $table->string('secondary_appearance_status')->nullable();
            $table->string('secondary_coding_na_status')->nullable(); // tri-state: Conform / Not Conform / N/A
            $table->string('secondary_attribute_status')->nullable();

            $table->string('tersier_identity_status')->nullable();
            $table->string('tersier_appearance_status')->nullable();
            $table->string('tersier_coding_na_status')->nullable();

            $table->decimal('standard_weight_mb', 10, 4)->nullable();
            $table->decimal('sum_weight_mb', 10, 4)->nullable();
            $table->string('line_leader_name')->nullable();

            $table->string('coding_machine')->nullable();
            $table->text('remarks')->nullable(); // required on this screen in legacy
            $table->string('decision')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_checks');
    }
};
