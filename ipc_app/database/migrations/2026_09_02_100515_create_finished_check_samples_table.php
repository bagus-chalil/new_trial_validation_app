<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per AQL parameter group (the ~18 legacy groups — Tersier/Secondary/Primary
        // Identity/Appearance/Coding/Attribute, Functional Test, Special Test — see
        // App\Models\FinishedCheckSample::PARAMETER_KEYS), replacing legacy's ~72 flattened
        // QST*/QSS*/QSP*/QSF* columns.
        Schema::create('finished_check_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_check_id')->constrained('finished_checks')->cascadeOnDelete();
            $table->string('parameter_key');
            $table->unsignedInteger('ac')->nullable();
            $table->unsignedInteger('cd')->nullable();
            $table->unsignedInteger('md')->nullable();
            $table->unsignedInteger('mnd')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['finished_check_id', 'parameter_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_check_samples');
    }
};
