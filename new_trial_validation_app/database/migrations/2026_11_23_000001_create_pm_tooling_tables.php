<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check Tooling (FR.PRNI.002.00, Packaging Proposal to Production Check) of
 * the Projects module, plus the Improvement "after" result fields that the
 * production Project Tracking app gained after 17 Nov (PROJECT_MODULE_PLAN.md
 * Fase 4b).
 *
 * Additive only, like every pm_* migration: new tables are guarded with
 * Schema::hasTable and new columns on the (still prod-empty) pm_* tables with
 * Schema::hasColumn, so a re-run is a no-op. No foreign keys: users and
 * pm_projects ids are plain columns, same as the other pm_* tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_toolings')) {
            Schema::create('pm_toolings', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 40)->nullable()->unique();
                $table->string('doc_code', 50)->default('FR.PRNI.002.00');
                $table->string('npd_no', 120)->default('');
                $table->date('request_date')->nullable();
                $table->string('requested_by_name', 100)->default('');
                $table->unsignedBigInteger('submitted_by_user_id')->nullable()->index();
                $table->string('submitted_by_name', 100)->default('');
                $table->dateTime('submitted_at')->nullable();

                // Menunggu Sampel, Sampel Sedang Dikirim, Sampel Diterima, Selesai Dicek
                $table->string('sample_status', 30)->default('Menunggu Sampel');
                $table->date('sample_send_date')->nullable();
                $table->text('sample_send_note')->nullable();
                $table->date('sample_received_date')->nullable();
                $table->text('sample_received_note')->nullable();
                $table->unsignedBigInteger('sample_received_by_user_id')->nullable();
                $table->string('sample_received_by_name', 100)->default('');
                $table->date('due_date')->nullable();
                $table->boolean('is_critical')->default(false);

                // Section A: request (PRNI)
                $table->string('client_code', 100)->default('');
                $table->string('product_name', 255)->default('');
                $table->string('marketing', 120)->default('');
                $table->string('packdev', 120)->default('');
                $table->string('product_type', 20)->default('skin_care');
                $table->string('packaging_type', 255)->default('');
                $table->string('quantity_po', 120)->default('');
                $table->string('launch_date', 120)->default('');
                $table->text('dimension_packaging')->nullable();
                $table->text('material_packaging')->nullable();
                $table->text('bom_primary')->nullable();
                $table->text('pm_ref_detail')->nullable();
                $table->json('links')->nullable();

                // Section B: formula (RNI)
                $table->text('formula_info')->nullable();
                $table->string('volume_suggest', 255)->default('');

                // Section C: tooling check (PIE)
                $table->string('recommended_machine', 255)->default('');
                $table->string('need_invest', 3)->default('');
                $table->text('need_invest_note')->nullable();
                $table->text('production_note')->nullable();
                $table->string('risk_level', 20)->default('');
                $table->unsignedBigInteger('pi_owner_user_id')->nullable();
                $table->string('pi_owner_name', 100)->default('');

                // Derived from approvals + sample status, except the manual
                // "Revisi / Hold" and "Ditolak" (see ToolingWorkflow).
                $table->string('status', 40)->default('Menunggu RNI & Sampel');
                $table->text('status_reason')->nullable();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                // Timeline shown on the detail page: imported legacy entries
                // ({at: epoch-ms, by, status, action, note}) and new ones
                // ({at: 'Y-m-d H:i:s', by, status, action: code, note}).
                $table->json('history_log')->nullable();
                $table->string('updated_by_name', 100)->default('');
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->index('status', 'idx_pm_toolings_status');
            });
        }

        if (! Schema::hasTable('pm_tooling_items')) {
            Schema::create('pm_tooling_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tooling_id')->index();
                $table->string('legacy_id', 40)->nullable();
                $table->string('tool', 160)->default('');
                $table->string('actual', 255)->default('');
                // OK, NOK, Need New, N/A (or empty)
                $table->string('status', 10)->default('');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_tooling_approvals')) {
            Schema::create('pm_tooling_approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tooling_id');
                // prni, rni, pi, production
                $table->string('role', 20);
                $table->boolean('approved')->default(false);
                $table->unsignedBigInteger('signer_user_id')->nullable();
                $table->string('signer_name', 120)->default('');
                $table->date('sign_date')->nullable();
                $table->dateTime('signed_at')->nullable();
                $table->text('note')->nullable();
                // password = e-signature in this app; legacy = imported drawn
                // signature (image kept for display only).
                $table->string('signature_method', 20)->default('password');
                $table->mediumText('signature_image')->nullable();
                $table->string('signed_by_account', 100)->default('');
                $table->timestamps();

                $table->unique(['tooling_id', 'role'], 'uq_pm_tooling_approvals_role');
            });
        }

        if (Schema::hasTable('pm_files') && ! Schema::hasColumn('pm_files', 'tooling_id')) {
            Schema::table('pm_files', function (Blueprint $table) {
                $table->unsignedBigInteger('tooling_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('pm_improvements') && ! Schema::hasColumn('pm_improvements', 'after_condition')) {
            Schema::table('pm_improvements', function (Blueprint $table) {
                $table->text('after_condition')->nullable();
                $table->text('after_result')->nullable();
                $table->date('after_date')->nullable();
                $table->string('after_pic', 100)->default('');
                $table->json('after_links')->nullable();
            });
        }

        if (Schema::hasTable('pm_improvement_files') && ! Schema::hasColumn('pm_improvement_files', 'phase')) {
            Schema::table('pm_improvement_files', function (Blueprint $table) {
                // before = proposal attachment, after = result evidence
                $table->string('phase', 10)->default('before');
            });
        }
    }

    public function down(): void
    {
        // Never drop pm_* tables or columns from a migration: they may hold migrated data.
    }
};
