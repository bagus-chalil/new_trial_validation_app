<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable audit trail, same reasoning as filling_check_revisions: packing_checks is
        // updateOrCreate'd in place so it only ever holds the *current* round's answers, and
        // without this an earlier round's checklist/remarks/decision would be silently
        // overwritten by the next one. Needed for reporting how QC's assessment evolved across
        // TH_PROGRESS rounds. line_leader_name/coding_machine are deliberately absent: they're
        // captured once on round 1 and constant thereafter, so a per-round copy adds nothing.
        Schema::create('packing_check_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_check_id')->constrained('packing_checks')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->boolean('finalize');

            $table->string('primary_bulk_status')->nullable();
            $table->string('primary_packaging_status')->nullable();
            $table->string('primary_capping_batch_exp_status')->nullable();
            $table->string('primary_na_number_status')->nullable();
            $table->string('primary_attribute_status')->nullable();
            $table->string('primary_functional_test_status')->nullable();

            $table->string('secondary_identity_status')->nullable();
            $table->string('secondary_appearance_status')->nullable();
            $table->string('secondary_coding_na_status')->nullable();
            $table->string('secondary_attribute_status')->nullable();

            $table->string('tersier_identity_status')->nullable();
            $table->string('tersier_appearance_status')->nullable();
            $table->string('tersier_coding_na_status')->nullable();

            $table->decimal('standard_weight_mb', 10, 4)->nullable();
            $table->decimal('sum_weight_mb', 10, 4)->nullable();
            $table->text('remarks')->nullable();
            $table->string('decision')->nullable();

            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['packing_check_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_check_revisions');
    }
};
