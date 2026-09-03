<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Confirmed with real IPC users 2026-09-03: the SOP no longer does the 30-sample
        // BottleData weighing sub-screen — AVERAGE_OF_EMPTY_BOTTLE_WEIGHT is entered once,
        // directly, on the Start_Check form itself (matches the legacy screen's own layout,
        // which has a single input box for it next to a now-unused "BottleData" button).
        Schema::dropIfExists('startup_bottle_weights');
    }

    public function down(): void
    {
        Schema::create('startup_bottle_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('startup_check_id')->constrained('startup_checks')->cascadeOnDelete();
            $table->unsignedTinyInteger('sample_no');
            $table->decimal('weight_value', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['startup_check_id', 'sample_no']);
        });
    }
};
