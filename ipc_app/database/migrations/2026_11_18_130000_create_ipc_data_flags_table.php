<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data-quality flags for a future AI/training export (same idea as ai_data_flags in
     * new_trial_validation_app). A brand-new side table on purpose: it LABELS rows in existing
     * tables without altering them, so no production table is touched. Rows are never deleted by
     * the app; an export simply skips what is flagged.
     */
    public function up(): void
    {
        if (Schema::hasTable('ipc_data_flags')) {
            return;
        }

        Schema::create('ipc_data_flags', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 60);
            $table->string('source_id', 60);
            $table->string('field', 40)->nullable();
            $table->string('flag', 40);
            $table->string('reason', 200)->nullable();
            $table->string('detected_by', 20)->default('rule');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['source_table', 'source_id', 'field', 'flag'], 'ipc_data_flags_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_data_flags');
    }
};
