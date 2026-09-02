<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_inspection_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('startup_inspection_id')->constrained('startup_inspections')->cascadeOnDelete();
            $table->foreignId('master_test_type_id')->constrained('master_test_types');
            $table->string('result')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['startup_inspection_id', 'master_test_type_id'], 'si_test_results_inspection_test_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_inspection_test_results');
    }
};
