<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects module (merge of the standalone "Project Tracking PIE" app, see
 * ../../../PROJECT_MODULE_PLAN.md). Additive only: every table is guarded
 * with Schema::hasTable() so this is a no-op on a database that already has
 * it, and nothing here ever touches an existing shared table. No foreign
 * keys on purpose — `users`/`master_options`/`products`/`trials_header` come
 * from the legacy-shared schema whose integer types we don't control.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_projects')) {
            Schema::create('pm_projects', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 40)->nullable()->unique();
                $table->string('code', 80)->default('');
                $table->string('name', 255);
                $table->string('category', 100)->default('General');
                $table->string('status', 50)->default('On Progress');
                $table->string('priority', 50)->default('Medium');
                $table->unsignedBigInteger('pic_user_id')->nullable();
                $table->string('pic_name', 100)->default('');
                $table->string('area', 100)->default('');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedSmallInteger('progress')->default(0);
                $table->text('description')->nullable();
                $table->string('vendor', 255)->default('');
                $table->decimal('budget', 18, 2)->default(0);
                $table->decimal('actual', 18, 2)->default(0);
                $table->string('po', 120)->default('');
                $table->unsignedBigInteger('machine_option_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('created_by_name', 100)->default('');
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->string('updated_by_name', 100)->default('');
                // Raw leftovers of the legacy metadata blob (change log + PO
                // list) kept verbatim so the import loses nothing.
                $table->json('legacy_meta')->nullable();
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->index('status', 'idx_pm_projects_status');
                $table->index('category', 'idx_pm_projects_category');
                $table->index('pic_user_id', 'idx_pm_projects_pic');
            });
        }

        if (! Schema::hasTable('pm_stages')) {
            Schema::create('pm_stages', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 60)->nullable()->unique();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('stage_type', 30)->default('General');
                $table->string('name', 160);
                $table->unsignedBigInteger('pic_user_id')->nullable();
                $table->string('pic_name', 100)->default('');
                $table->date('plan_start')->nullable();
                $table->date('plan_end')->nullable();
                $table->date('actual_start')->nullable();
                $table->date('actual_end')->nullable();
                $table->unsignedSmallInteger('progress')->default(0);
                $table->boolean('is_parallel')->default(false);
                $table->string('status', 50)->default('Not Started');
                $table->text('note')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('trial_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_milestones')) {
            Schema::create('pm_milestones', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 60)->nullable()->unique();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('name', 160);
                $table->date('due_date')->nullable();
                $table->string('status', 50)->default('Pending');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_quotations')) {
            Schema::create('pm_quotations', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 60)->nullable()->unique();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('vendor_option_id')->nullable();
                $table->string('vendor_name', 255)->default('');
                $table->string('quotation_no', 120)->default('');
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('currency', 8)->default('IDR');
                $table->date('quotation_date')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('status', 50)->default('Diterima');
                $table->text('note')->nullable();
                $table->string('link', 500)->default('');
                $table->unsignedBigInteger('file_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_files')) {
            Schema::create('pm_files', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 60)->nullable()->unique();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->unsignedBigInteger('stage_id')->nullable()->index();
                // project | stage | quotation | tooling (tooling = orphan files
                // of the legacy Check Tooling feature, not migrated as data).
                $table->string('context', 20)->default('project');
                $table->string('file_name', 255);
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('stored_name', 255);
                $table->text('description')->nullable();
                $table->string('uploaded_by_name', 100)->default('');
                $table->dateTime('uploaded_at')->nullable();
                // True when the physical file was not found at import time.
                $table->boolean('is_missing')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_stage_templates')) {
            Schema::create('pm_stage_templates', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 60)->nullable()->unique();
                $table->string('name', 160);
                $table->string('category', 100)->default('');
                $table->string('owner_name', 100)->default('');
                $table->json('stages')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Never drop pm_* tables from a migration: they may hold migrated data.
    }
};
