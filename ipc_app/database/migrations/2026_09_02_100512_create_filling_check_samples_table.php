<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filling_check_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filling_check_id')->constrained('filling_checks')->cascadeOnDelete();
            $table->unsignedTinyInteger('sample_no');
            $table->decimal('weight_value', 10, 4)->nullable();
            // Legacy only computes/stores a per-sample result for samples 1-5 of 10 (a real
            // limitation of the source app, not a guess) — nullable, left blank for samples 6-10.
            $table->string('weight_result')->nullable();
            $table->timestamps();

            $table->unique(['filling_check_id', 'sample_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filling_check_samples');
    }
};
