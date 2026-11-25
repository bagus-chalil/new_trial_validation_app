<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 1 (see ../../TRIAL_VALIDATION_V2_PLAN.md):
 * the Case (parent number) foundation. Additive only: new `vd_*` tables,
 * hasTable-guarded, no foreign keys, nothing altered on the shared legacy
 * tables (trials_header, products, ...). Never dropped from a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vd_cases')) {
            Schema::create('vd_cases', function (Blueprint $table) {
                $table->id();
                $table->string('case_no', 40)->unique();
                // NPD, IMP, MIX
                $table->string('case_type', 10)->index();
                // YYMM of the first trial / of creation; part of the number.
                $table->string('period', 4);
                $table->unsignedInteger('seq');
                $table->unsignedBigInteger('product_id')->nullable()->index();
                // Name snapshot for a Case with no master product (yet).
                $table->string('product_name', 255)->default('');
                // backfill, manual
                $table->string('source', 20)->default('manual');
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();

                $table->unique(['case_type', 'period', 'seq'], 'uq_vd_cases_number');
            });
        }

        if (! Schema::hasTable('vd_case_counters')) {
            Schema::create('vd_case_counters', function (Blueprint $table) {
                $table->id();
                $table->string('case_type', 10);
                $table->string('period', 4);
                $table->unsignedInteger('last_seq')->default(0);
                $table->timestamps();

                // The generator locks this row, never MAX()+1.
                $table->unique(['case_type', 'period'], 'uq_vd_case_counters');
            });
        }

        if (! Schema::hasTable('vd_case_links')) {
            Schema::create('vd_case_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('case_id')->index();
                // trial, tooling, project, line_config, mixing, ipc_batch, im, qco
                $table->string('kind', 20);
                $table->unsignedBigInteger('ref_id')->nullable();
                // Always set: the id as text, or the foreign business key (IM / QCO / batch no).
                $table->string('ref_key', 120);
                $table->unsignedInteger('attempt_no')->nullable();
                $table->unsignedBigInteger('linked_by')->nullable();
                $table->timestamps();

                $table->unique(['kind', 'ref_key'], 'uq_vd_case_links_ref');
            });
        }

        // Side table (trials_header must not be ALTERed): per-trial dates and
        // the client approval evidence behind "Pass with Approval Client".
        if (! Schema::hasTable('vd_trial_meta')) {
            Schema::create('vd_trial_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trial_id')->unique();
                $table->date('pm_ready_date')->nullable();
                $table->date('bulk_ready_date')->nullable();
                // True when the dates were defaulted (H-1 of creation) by the
                // backfill, so lead-time KPIs can leave them out.
                $table->boolean('dates_defaulted')->default(false);
                $table->boolean('client_approved')->default(false);
                $table->text('client_approval_note')->nullable();
                $table->dateTime('client_approved_at')->nullable();
                $table->unsignedBigInteger('client_approved_by_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Never drop vd_* tables from a migration: they hold Case numbers.
    }
};
