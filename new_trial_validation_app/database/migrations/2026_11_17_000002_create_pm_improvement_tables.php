<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Improvement board of the Projects module — additive, hasTable-guarded (see the pm_project_tables migration). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_improvements')) {
            Schema::create('pm_improvements', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 40)->nullable()->unique();
                $table->string('title', 255);
                $table->string('department', 100)->default('');
                $table->unsignedBigInteger('submitted_by_user_id')->nullable()->index();
                $table->string('submitted_by_name', 100)->default('');
                $table->dateTime('submitted_at')->nullable();
                $table->string('type', 50)->default('');
                $table->text('issue')->nullable();
                $table->text('background')->nullable();
                $table->text('purpose')->nullable();
                $table->text('goals')->nullable();
                $table->string('saving_mp', 255)->default('');
                $table->string('quality_metric', 255)->default('');
                $table->json('links')->nullable();
                // Baru, Review PI, Menunggu Data, Disetujui, Tidak Perlu Improvement
                $table->string('status', 50)->default('Baru');
                // Pending, To Project, Suggestion Only
                $table->string('decision', 50)->default('Pending');
                $table->string('pi_owner', 100)->default('');
                $table->text('pi_feedback')->nullable();
                $table->text('pi_suggestion')->nullable();
                $table->text('pi_action')->nullable();
                $table->text('pi_reason')->nullable();
                $table->json('feedback_log')->nullable();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->string('updated_by_name', 100)->default('');
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->index('status', 'idx_pm_improvements_status');
            });
        }

        if (! Schema::hasTable('pm_improvement_files')) {
            Schema::create('pm_improvement_files', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 60)->nullable()->unique();
                $table->unsignedBigInteger('improvement_id')->index();
                $table->string('file_name', 255);
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('stored_name', 255);
                $table->text('description')->nullable();
                $table->dateTime('uploaded_at')->nullable();
                $table->boolean('is_missing')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Never drop pm_* tables from a migration: they may hold migrated data.
    }
};
