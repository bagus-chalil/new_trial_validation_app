<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supplementary documents (PDF/images) a trial's drafter or any of its
     * reviewers can attach at any point in the trial's lifecycle — even after
     * Approved/Rejected — separate from the wizard Step 5 evidence photos in
     * `trial_attachment_files`, which lock with the rest of the trial once it
     * leaves the editable statuses.
     *
     * Entirely new, Laravel-only table with no legacy equivalent, so no
     * legacy-compatibility concern; the Schema::hasTable guard just keeps
     * this idempotent against the shared DB like every other migration here.
     * Files live on the private `local` disk (storage/app/private), not the
     * shared legacy_uploads directory, since legacy never reads them.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trial_additional_attachments')) {
            Schema::create('trial_additional_attachments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('trial_id')->index();
                $table->string('original_name');
                $table->string('file_name');
                $table->string('mime_type', 100);
                $table->unsignedInteger('size_bytes');
                $table->string('description', 500)->nullable();
                $table->unsignedInteger('uploaded_by_user_id')->nullable();
                $table->string('uploaded_by_name', 150)->nullable();
                $table->string('uploader_role', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_additional_attachments');
    }
};
