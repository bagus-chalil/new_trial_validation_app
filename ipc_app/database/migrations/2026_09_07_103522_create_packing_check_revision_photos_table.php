<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_check_revision_photos', function (Blueprint $table) {
            $table->id();
            // Named explicitly (not via constrained()'s default naming) — same 64-char
            // identifier limit workaround already used by filling_check_revision_samples.
            $table->foreignId('packing_check_revision_id');
            $table->foreign('packing_check_revision_id', 'pc_revision_photos_revision_fk')
                ->references('id')->on('packing_check_revisions')->cascadeOnDelete();
            $table->string('field_label');
            // ipc_attachments now accumulates one row per upload for the packing stage instead
            // of overwriting (see PackingCheckController::uploadPhoto()), so this just points at
            // whichever attachment was current at the moment this revision was saved — nullable
            // FK (not cascade-deleted) since the attachment row outliving this pointer is fine,
            // and file_path is denormalized alongside it so the PDF report never needs a join.
            $table->foreignId('ipc_attachment_id')->nullable()->constrained('ipc_attachments')->nullOnDelete();
            $table->string('file_path');
            $table->timestamps();

            $table->unique(['packing_check_revision_id', 'field_label'], 'pc_revision_photos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_check_revision_photos');
    }
};
