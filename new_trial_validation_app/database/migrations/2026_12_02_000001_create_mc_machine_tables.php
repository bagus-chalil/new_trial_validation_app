<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machine master = "Cosmax Line Specification Database 2026" (Line Master +
 * Equipment List, maintained by the line maintainers) plus plant/location.
 * Three levels: plant -> line -> equipment. `line_code` (SC-01, MX-SC01) and
 * `equipment_code` (SC-01-E01) are the permanent, unique trace keys IPC and
 * other apps reference. Additive only: new `mc_*` tables, hasTable-guarded,
 * no foreign keys (shared-DB rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mc_plants')) {
            Schema::create('mc_plants', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 150);
                $table->string('address', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mc_lines')) {
            Schema::create('mc_lines', function (Blueprint $table) {
                $table->id();
                $table->string('line_code', 30)->unique();
                $table->string('category', 40)->index();
                $table->string('line_type', 60)->nullable();
                $table->string('name', 200);
                // Location: plant -> building -> floor -> area/room.
                $table->unsignedBigInteger('plant_id')->nullable()->index();
                $table->string('building', 100)->nullable();
                $table->string('floor', 50)->nullable();
                $table->string('area', 120)->nullable();
                $table->string('status', 30)->default('To be verified')->index();
                $table->text('products')->nullable();
                $table->decimal('rated_capacity', 12, 2)->nullable();
                $table->string('capacity_unit', 30)->nullable();
                $table->string('pack_format', 255)->nullable();
                $table->string('automation_level', 30)->nullable();
                $table->unsignedSmallInteger('manpower')->nullable();
                $table->string('pic', 120)->nullable();
                $table->string('layout_link', 500)->nullable();
                $table->string('tooling_link', 500)->nullable();
                $table->text('remarks')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mc_equipment')) {
            Schema::create('mc_equipment', function (Blueprint $table) {
                $table->id();
                $table->string('equipment_code', 40)->unique();
                $table->unsignedBigInteger('line_id')->index();
                $table->unsignedSmallInteger('process_step')->nullable();
                $table->string('name', 200);
                $table->string('equipment_type', 60)->nullable();
                $table->string('manufacturer', 150)->nullable();
                $table->string('model', 120)->nullable();
                $table->string('serial_no', 120)->nullable();
                $table->unsignedSmallInteger('year_made')->nullable();
                $table->string('country', 80)->nullable();
                $table->decimal('rated_capacity', 12, 2)->nullable();
                $table->string('capacity_unit', 30)->nullable();
                $table->decimal('max_capacity', 12, 2)->nullable();
                $table->string('heads', 150)->nullable();
                $table->string('working_volume', 150)->nullable();
                $table->string('temp_range', 80)->nullable();
                $table->string('pressure', 80)->nullable();
                $table->string('power_kw', 40)->nullable();
                $table->string('voltage', 80)->nullable();
                $table->string('compressed_air', 80)->nullable();
                $table->string('other_utility', 150)->nullable();
                $table->string('dimension', 100)->nullable();
                $table->string('weight_kg', 40)->nullable();
                $table->text('other_spec')->nullable();
                $table->string('condition', 30)->nullable();
                $table->string('manual_status', 30)->nullable();
                $table->string('manual_link', 500)->nullable();
                $table->string('drawing_link', 500)->nullable();
                $table->unsignedSmallInteger('qty')->nullable();
                $table->unsignedSmallInteger('units_good')->nullable();
                $table->unsignedSmallInteger('units_attention')->nullable();
                $table->unsignedSmallInteger('units_maintenance')->nullable();
                $table->unsignedSmallInteger('units_out_of_service')->nullable();
                $table->unsignedSmallInteger('units_to_verify')->nullable();
                $table->text('condition_detail')->nullable();
                $table->text('remarks')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mc_equipment');
        Schema::dropIfExists('mc_lines');
        Schema::dropIfExists('mc_plants');
    }
};
