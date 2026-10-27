<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->insertOrIgnore([
            ['code' => 'microbiologist', 'label' => 'Microbiologist', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Micro Check block of the paper form FR.QAC.193.00: Total Plate Count / Yeast & Mold /
        // Pathogen, each a Result + Std (cfu/gr). Free text on purpose — the paper form holds
        // values like "<10", "<100" and "(-)", not numbers.
        Schema::create('micro_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('total_plate_count_result', 50);
            $table->string('total_plate_count_std', 50);
            $table->string('yeast_mold_result', 50);
            $table->string('yeast_mold_std', 50);
            $table->string('pathogen_result', 50);
            $table->string('pathogen_std', 50);
            $table->unsignedInteger('save_count')->default(1);
            $table->timestamps();
        });

        // One immutable row per save (who / when / what), so edits made before Finished Good is
        // finalized stay traceable — same idea as finished_check_revisions.
        Schema::create('micro_check_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('micro_check_id')->constrained('micro_checks')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->foreignId('user_id')->constrained('users');
            $table->string('total_plate_count_result', 50);
            $table->string('total_plate_count_std', 50);
            $table->string('yeast_mold_result', 50);
            $table->string('yeast_mold_std', 50);
            $table->string('pathogen_result', 50);
            $table->string('pathogen_std', 50);
            $table->timestamps();

            $table->unique(['micro_check_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micro_check_revisions');
        Schema::dropIfExists('micro_checks');
        DB::table('roles')->where('code', 'microbiologist')->delete();
    }
};
