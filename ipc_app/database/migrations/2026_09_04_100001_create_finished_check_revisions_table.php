<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable audit trail mirroring filling_check_revisions/packing_check_revisions: every
        // Save/Save & End writes a new row here (never updated) alongside the "current state"
        // update on finished_checks itself, so an earlier save's quantities/decision/remarks
        // survive later saves instead of being silently overwritten.
        Schema::create('finished_check_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_check_id')->constrained('finished_checks')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->boolean('finalize');

            $table->decimal('quantity_wi', 12, 2)->nullable();
            $table->decimal('masterbox', 12, 2)->nullable();
            $table->decimal('no_pallet_qty', 12, 2)->nullable();
            $table->unsignedInteger('quantity_sampling_aql')->nullable();
            $table->unsignedInteger('quantity_sample_aql_cd')->nullable();
            $table->unsignedInteger('quantity_sample_aql_md')->nullable();
            $table->unsignedInteger('quantity_sample_aql_mnd')->nullable();
            $table->unsignedInteger('quantity_special_inspection')->nullable();
            $table->unsignedInteger('quantity_special_inspection_cd')->nullable();
            $table->unsignedInteger('quantity_special_inspection_md')->nullable();
            $table->unsignedInteger('quantity_special_inspection_mnd')->nullable();
            $table->string('line_leader_name')->nullable();
            $table->string('disposition')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['finished_check_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_check_revisions');
    }
};
