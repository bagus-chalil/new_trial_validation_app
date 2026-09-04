<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finished_check_revision_samples', function (Blueprint $table) {
            $table->id();
            // Named explicitly (not via constrained()'s default naming) — the auto-generated FK
            // name for this table+column combination runs past MySQL's 64-char identifier limit,
            // same class of fix already applied to filling_check_revision_samples and a few other
            // tables in this app.
            $table->foreignId('finished_check_revision_id');
            $table->foreign('finished_check_revision_id', 'fnc_revision_samples_revision_fk')
                ->references('id')->on('finished_check_revisions')->cascadeOnDelete();
            $table->string('parameter_key');
            $table->unsignedInteger('ac')->nullable();
            $table->unsignedInteger('cd')->nullable();
            $table->unsignedInteger('md')->nullable();
            $table->unsignedInteger('mnd')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['finished_check_revision_id', 'parameter_key'], 'fnc_revision_samples_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_check_revision_samples');
    }
};
