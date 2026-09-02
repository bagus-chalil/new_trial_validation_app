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
            $table->timestamps();

            $table->unique(['filling_check_id', 'sample_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filling_check_samples');
    }
};
