<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 6 (see ../../TRIAL_VALIDATION_V2_PLAN.md): IM
 * (Instruksi Mutu) master owned by QA. One master per FG Code, N revisions
 * (Draft -> review -> QA approval -> Effective / Superseded), structured
 * parameters, approved-trial evidence, drawings/photos and the QA sign-off
 * chain. Additive only: new `vd_*` tables, hasTable-guarded, no foreign keys.
 * The IM number itself stays in vd_im_numbers (Tahap 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vd_im_masters')) {
            Schema::create('vd_im_masters', function (Blueprint $table) {
                $table->id();
                // Official FG Code (iERP), UPPER CASE. One IM master per FG.
                $table->string('fg_code', 60)->unique();
                $table->string('brand_code', 10)->index();
                $table->string('product_part', 200);
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name', 255)->default('');
                $table->string('customer', 150)->default('');
                // Other Bulk / WIP codes the same IM covers (list of strings).
                $table->json('bulk_wip_refs')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('created_by_name', 150)->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vd_im_revisions')) {
            Schema::create('vd_im_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_id')->index();
                $table->unsignedSmallInteger('revision_no')->default(1);
                $table->string('im_number', 255)->index();
                $table->unsignedBigInteger('im_number_id')->nullable()->index();
                // Draft, In Review, Ready for Approval, Need Revision, Rejected,
                // Scheduled (approved, release date in the future), Effective, Superseded
                $table->string('status', 20)->default('Draft')->index();

                $table->string('description', 255)->default('');
                $table->string('appearance', 100)->default('');
                $table->string('mfg_category', 50)->default('');
                $table->string('customer', 150)->default('');
                $table->string('customer_ref', 150)->default('');
                $table->string('line_label', 200)->default('');
                $table->date('issue_date')->nullable();
                // Release date: approved future version becomes Effective here.
                $table->date('effective_from')->nullable()->index();
                $table->dateTime('effective_at')->nullable();
                $table->dateTime('superseded_at')->nullable();
                $table->string('revision_reason', 255)->default('');
                $table->string('replaces_number', 255)->nullable();
                // Free-form blocks of the worksheet (packaging text, coding rules, notes).
                $table->json('content')->nullable();
                // Reference to the signed paper IM when an existing IM is re-created here.
                $table->string('legacy_reference', 255)->nullable();
                $table->boolean('is_onboarded')->default(false);

                $table->unsignedBigInteger('prepared_by_user_id')->nullable()->index();
                $table->string('prepared_by_name', 150)->default('');
                $table->unsignedBigInteger('approver_user_id')->nullable()->index();
                $table->string('pending_with', 255)->default('');
                $table->unsignedSmallInteger('review_round')->default(1);
                $table->text('approval_comment')->nullable();
                $table->string('approved_by', 150)->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('rejected_by', 150)->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->timestamps();

                $table->unique(['master_id', 'revision_no'], 'uq_vd_im_revisions_no');
            });
        }

        if (! Schema::hasTable('vd_im_parameters')) {
            Schema::create('vd_im_parameters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('revision_id')->index();
                // bom, filling_point, appearance_point, packing_point, coding,
                // weight, surveillance_initial, surveillance_periodic
                $table->string('section', 30)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                // Item no / bulk code / FG-WIP code, depending on the section.
                $table->string('code', 80)->nullable();
                $table->string('name', 255)->default('');
                $table->string('category', 80)->nullable();
                $table->text('description')->nullable();
                $table->text('specification')->nullable();
                $table->string('frequency', 255)->nullable();
                $table->decimal('value_min', 12, 3)->nullable();
                $table->decimal('value_max', 12, 3)->nullable();
                $table->string('unit', 20)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vd_im_evidence')) {
            Schema::create('vd_im_evidence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('revision_id')->index();
                // trial (an Approved trial) or document (other official source)
                $table->string('kind', 12)->default('trial');
                $table->unsignedBigInteger('trial_id')->nullable()->index();
                $table->unsignedBigInteger('case_id')->nullable();
                $table->string('title', 255)->default('');
                $table->string('reference', 255)->default('');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('added_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vd_im_files')) {
            Schema::create('vd_im_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('revision_id')->index();
                $table->string('category', 40)->default('appearance');
                $table->string('caption', 255)->default('');
                $table->string('original_name', 255);
                $table->string('file_name', 80);
                $table->string('mime', 100);
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vd_im_signoffs')) {
            Schema::create('vd_im_signoffs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('revision_id')->index();
                $table->unsignedSmallInteger('round')->default(1);
                // prepared, review, approved
                $table->string('stage', 12)->index();
                $table->string('role_label', 80)->default('');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 150)->default('');
                // Pending, Signed, Returned
                $table->string('status', 10)->default('Pending');
                $table->text('comment')->nullable();
                $table->dateTime('signed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Never drop vd_* tables from a migration: they hold the quality standard.
    }
};
