<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filling_check_revision_samples', function (Blueprint $table) {
            $table->id();
            // Named explicitly (not via constrained()'s default naming) — the auto-generated
            // name for this table+column combination runs past MySQL's 64-char identifier
            // limit, same class of fix already applied to a few other tables in this app.
            $table->foreignId('filling_check_revision_id');
            $table->foreign('filling_check_revision_id', 'fc_revision_samples_revision_fk')
                ->references('id')->on('filling_check_revisions')->cascadeOnDelete();
            $table->unsignedTinyInteger('sample_no');
            $table->decimal('weight_value', 10, 4)->nullable();
            $table->string('weight_result')->nullable();
            $table->timestamps();

            $table->unique(['filling_check_revision_id', 'sample_no'], 'fc_revision_samples_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filling_check_revision_samples');
    }
};
