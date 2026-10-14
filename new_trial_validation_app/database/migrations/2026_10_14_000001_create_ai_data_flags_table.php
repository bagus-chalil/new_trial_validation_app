<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data-quality flags for the AI-readiness export (see App\Services\Ai).
     *
     * A side table on purpose: it labels rows in the shared legacy tables
     * (trials_header, trials_review, trials_results) without ALTERing any of
     * them, so the legacy app and the running Laravel app are untouched.
     * Brand-new, Laravel-only table; the hasTable guard keeps it idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_data_flags')) {
            Schema::create('ai_data_flags', function (Blueprint $table) {
                $table->increments('id');
                $table->string('source_table', 60);
                // String because trials_results has a composite key ("trial_id:parameter_id").
                $table->string('source_id', 60);
                $table->string('field', 40)->nullable();
                $table->unsignedInteger('trial_id')->nullable()->index();
                $table->string('flag', 40);
                $table->string('reason', 200)->nullable();
                $table->string('detected_by', 20)->default('rule');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['source_table', 'source_id', 'field', 'flag'], 'ai_data_flags_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_data_flags');
    }
};
