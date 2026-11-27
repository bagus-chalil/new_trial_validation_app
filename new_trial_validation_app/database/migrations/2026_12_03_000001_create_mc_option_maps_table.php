<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps the Trial `machine_used` option names (master_options, type
 * machine_used) to the machine master (mc_lines / mc_equipment). Keyed by the
 * option NAME because trials_header.machine_used stores plain text names, so
 * old trials (and renamed/removed options) stay resolvable. Additive only:
 * trials and master_options are never touched, no foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mc_option_maps')) {
            return;
        }

        Schema::create('mc_option_maps', function (Blueprint $table) {
            $table->id();
            $table->string('option_name', 190)->unique();
            $table->unsignedBigInteger('line_id')->nullable()->index();
            $table->unsignedBigInteger('equipment_id')->nullable()->index();
            // auto | suggested | manual | not_applicable
            $table->string('status', 20)->index();
            $table->decimal('score', 3, 2)->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('mapped_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mc_option_maps');
    }
};
