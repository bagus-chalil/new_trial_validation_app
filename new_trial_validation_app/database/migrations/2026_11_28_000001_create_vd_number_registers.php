<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 3 (see ../../TRIAL_VALIDATION_V2_PLAN.md):
 * registers for the QCO number (issued by PRNI) and the IM number (issued by
 * QA). Additive only: new `vd_*` tables, hasTable-guarded, no foreign keys.
 * QCO sequences reuse the locked vd_number_counters rows (scope `qco`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vd_qco_numbers')) {
            Schema::create('vd_qco_numbers', function (Blueprint $table) {
                $table->id();
                // QCO-{YYYY}-{NNNN}
                $table->string('number', 20)->unique();
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('seq');
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name', 255)->default('');
                $table->string('client', 150)->default('')->index();
                // YYYY-MM
                $table->string('sales_month', 7)->nullable();
                $table->unsignedBigInteger('qty_po')->nullable();
                $table->text('notes')->nullable();
                // Issued, Void (a void number is never reused)
                $table->string('status', 10)->default('Issued')->index();
                $table->string('void_reason', 255)->nullable();
                $table->unsignedBigInteger('issued_by_user_id')->nullable();
                $table->string('issued_by_name', 150)->default('');
                $table->timestamps();

                $table->unique(['year', 'seq'], 'uq_vd_qco_numbers_seq');
            });
        }

        // Customer / brand code used inside IM numbers (BHM, ABL, ...).
        if (! Schema::hasTable('vd_im_brand_codes')) {
            Schema::create('vd_im_brand_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 150)->default('');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vd_im_numbers')) {
            Schema::create('vd_im_numbers', function (Blueprint $table) {
                $table->id();
                // Normalised: UPPER CASE, single spaces, IM-{CODE}-{PRODUCT}[ V{n}]
                $table->string('number', 255)->unique();
                $table->string('brand_code', 10)->index();
                // The product part of the number, e.g. "BHUMI CALMING CLEANSER 100ML".
                $table->string('product_part', 200);
                // 1 = no suffix, 2 => " V2", ...
                $table->unsignedSmallInteger('version_no')->default(1);
                $table->unsignedBigInteger('replaces_id')->nullable()->index();
                // Free text when the replaced IM is a legacy one not in this register.
                $table->string('replaces_number', 255)->nullable();
                $table->string('revision_reason', 255)->nullable();
                $table->date('issue_date')->nullable();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                // FG / WIP codes the IM covers (matched against IPC fg_code).
                $table->json('fg_codes')->nullable();
                $table->string('line_machine', 200)->default('');
                $table->string('customer', 150)->default('');
                $table->string('customer_ref', 150)->default('');
                // Active, Superseded (a newer version exists), Void
                $table->string('status', 12)->default('Active')->index();
                $table->string('void_reason', 255)->nullable();
                $table->unsignedBigInteger('issued_by_user_id')->nullable();
                $table->string('issued_by_name', 150)->default('');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Never drop vd_* tables from a migration: they hold issued numbers.
    }
};
