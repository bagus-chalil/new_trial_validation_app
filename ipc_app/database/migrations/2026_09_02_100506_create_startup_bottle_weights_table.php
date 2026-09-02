<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('startup_bottle_weights');
    }
};
