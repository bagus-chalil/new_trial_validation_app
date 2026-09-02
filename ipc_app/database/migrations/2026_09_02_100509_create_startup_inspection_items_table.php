<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('startup_inspection_id')->constrained('startup_inspections')->cascadeOnDelete();
            $table->string('parameter_key');
            $table->string('status')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['startup_inspection_id', 'parameter_key'], 'si_items_inspection_parameter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_inspection_items');
    }
};
