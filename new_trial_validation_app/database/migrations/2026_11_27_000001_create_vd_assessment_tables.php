<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 2 (see ../../TRIAL_VALIDATION_V2_PLAN.md):
 * Validation Assessment (creates the Case), weekly Validation Plan, and the
 * Improvement number. Additive only: new `vd_*` tables, hasTable-guarded,
 * no foreign keys, nothing altered on pm_* or the shared legacy tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vd_assessments')) {
            Schema::create('vd_assessments', function (Blueprint $table) {
                $table->id();
                // One Assessment per Case; saving it creates the Case.
                $table->unsignedBigInteger('case_id')->unique();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                // SKU name as typed when the SKU is not in the master yet.
                $table->string('product_name', 255)->default('');
                $table->date('receive_date')->nullable()->index();
                $table->string('client', 150)->default('')->index();
                // YYYY-MM
                $table->string('sales_month', 7)->nullable()->index();
                $table->unsignedBigInteger('qty_po')->nullable();
                $table->string('packaging_type', 120)->default('');
                $table->json('validation_scope')->nullable();
                $table->string('recommended_machine', 255)->default('');
                $table->string('qco_no', 80)->default('');
                $table->string('qco_link', 500)->default('');
                $table->date('pm_received_date')->nullable();
                $table->boolean('pm_na')->default(false);
                $table->date('bulk_received_date')->nullable();
                $table->boolean('bulk_na')->default(false);
                // Low, Medium, High: set by PIE at assessment (D9).
                $table->string('risk_level', 10)->default('Medium')->index();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('created_by_name', 150)->default('');
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('vd_validation_plans')) {
            Schema::create('vd_validation_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('case_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name', 255)->default('');
                // Monday of the requested ISO week.
                $table->date('week_start')->index();
                // Requested, Confirmed, Cancelled
                $table->string('status', 12)->default('Requested')->index();
                $table->text('notes')->nullable();
                $table->text('ppic_note')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->string('requested_by_name', 150)->default('');
                $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
                $table->string('confirmed_by_name', 150)->default('');
                $table->dateTime('confirmed_at')->nullable();
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        // Improvement numbers live beside pm_improvements (never a new column on it).
        if (! Schema::hasTable('vd_improvement_numbers')) {
            Schema::create('vd_improvement_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('improvement_id')->unique();
                $table->string('number', 20)->unique();
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('seq');
                $table->timestamps();

                $table->unique(['year', 'seq'], 'uq_vd_improvement_numbers_seq');
            });
        }

        // Generic locked counter row (scope + period), same idea as vd_case_counters.
        if (! Schema::hasTable('vd_number_counters')) {
            Schema::create('vd_number_counters', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 30);
                $table->string('period', 10);
                $table->unsignedInteger('last_seq')->default(0);
                $table->timestamps();

                $table->unique(['scope', 'period'], 'uq_vd_number_counters');
            });
        }
    }

    public function down(): void
    {
        // Never drop vd_* tables from a migration: they hold issued numbers.
    }
};
