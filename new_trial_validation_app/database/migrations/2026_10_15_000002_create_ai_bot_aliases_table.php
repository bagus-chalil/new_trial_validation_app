<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phrases an admin teaches the (rule-based) trial bot from the GUI, so new
     * wordings never need a code change. Each phrase points at one of the
     * bot's fixed intents (never free-text answers), or is marked "ignore" to
     * hide a pointless question from the unanswered list.
     *
     * Brand-new Laravel-only table (no legacy impact); hasTable keeps it idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_bot_aliases')) {
            Schema::create('ai_bot_aliases', function (Blueprint $table) {
                $table->increments('id');
                $table->string('phrase', 200)->unique();
                $table->string('target', 30);
                // Only used when target = trials_by_status (null = any status).
                $table->string('status', 30)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_aliases');
    }
};
