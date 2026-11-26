<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 4 (see ../../TRIAL_VALIDATION_V2_PLAN.md):
 * Mixing as a Case kind (MIX). Additive only: new `vd_mixing*` tables,
 * hasTable-guarded, no foreign keys. trials_header is NOT touched (the legacy
 * app still uses it). A Mixing record is tied to its Case through
 * vd_case_links kind `mixing`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vd_mixings')) {
            Schema::create('vd_mixings', function (Blueprint $table) {
                $table->id();
                // MIX-{YYYYMMDD}-{RANDOM4}
                $table->string('mixing_code', 40)->unique();
                $table->unsignedBigInteger('case_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name', 255)->default('');
                $table->string('product_type', 100)->default('');
                $table->string('batch_no', 100)->default('');
                $table->decimal('batch_size_kg', 12, 2)->nullable();
                $table->string('mixing_machine', 150)->default('');
                $table->date('mixing_date')->nullable();
                $table->text('objective')->nullable();
                // Revision of the batch record (D8): reference text; files go in vd_mixing_files.
                $table->string('batch_record_no', 100)->default('');
                $table->string('batch_record_revision', 50)->default('');
                $table->text('revision_summary')->nullable();
                // Draft, In Review, Ready for Approval, Approved, Need Revision, Rejected
                $table->string('progress_status', 30)->default('Draft')->index();
                // Header, Validation, Attachment, Review, Approval, Revision, Closed
                $table->string('current_step', 30)->default('Validation');
                $table->unsignedInteger('revision_no')->default(0);
                $table->string('pending_with', 255)->default('');
                $table->unsignedBigInteger('approver_user_id')->nullable()->index();
                $table->string('final_decision', 30)->nullable();
                $table->string('approved_by', 150)->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('rejected_by', 150)->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->text('approval_comment')->nullable();
                $table->string('created_by', 150)->default('')->index();
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
            });
        }

        if (! Schema::hasTable('vd_mixing_results')) {
            Schema::create('vd_mixing_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mixing_id');
                $table->unsignedBigInteger('parameter_id');
                $table->string('decision', 10)->default('OK');
                $table->string('result_value', 255)->nullable();
                $table->text('remark')->nullable();
                $table->timestamps();

                $table->unique(['mixing_id', 'parameter_id'], 'uq_vd_mixing_results');
            });
        }

        if (! Schema::hasTable('vd_mixing_files')) {
            Schema::create('vd_mixing_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mixing_id')->index();
                // Revisi Batch Record, Foto Proses, Hasil Uji, Lainnya
                $table->string('category', 60);
                $table->string('original_name', 255);
                $table->string('file_name', 80);
                $table->string('mime', 100)->default('');
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('caption', 255)->nullable();
                $table->string('uploaded_by', 150)->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vd_mixing_reviews')) {
            Schema::create('vd_mixing_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mixing_id');
                $table->string('department', 50);
                $table->unsignedInteger('review_round')->default(1);
                // Pending, Reviewed
                $table->string('status', 15)->default('Pending');
                $table->unsignedBigInteger('reviewer_user_id')->nullable();
                $table->string('reviewer_name', 150)->nullable();
                $table->text('comment')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['mixing_id', 'department', 'review_round'], 'uq_vd_mixing_reviews');
            });
        }
    }

    public function down(): void
    {
        // Never drop vd_* tables from a migration: they hold validation records.
    }
};
