<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_inspection_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('startup_inspection_id')->constrained('startup_inspections')->cascadeOnDelete();
            $table->unsignedTinyInteger('sample_no');
            $table->decimal('volume', 10, 4)->nullable();
            $table->decimal('weight', 10, 4)->nullable();
            $table->decimal('weight_master_box', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['startup_inspection_id', 'sample_no'], 'si_samples_inspection_sample_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_inspection_samples');
    }
};
