<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every question asked to the (rule-based) trial bot, with the intent it
     * matched and the user's thumbs up/down. This is the bot's training source:
     * unmatched questions become the backlog of new intents, thumbs-down shows
     * what to fix, and curated thumbs-up rows become the golden evaluation set.
     *
     * Brand-new Laravel-only table (no legacy impact); the hasTable guard keeps
     * it idempotent. The user is stored as a stable pseudonym, never the email.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_bot_interactions')) {
            Schema::create('ai_bot_interactions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('user_ref', 20)->nullable();
                $table->string('locale', 5)->default('id');
                $table->string('question', 500);
                $table->string('intent', 40)->nullable();
                $table->boolean('matched')->default(false);
                $table->string('answer_summary', 500)->nullable();
                // 1 = thumbs up, -1 = thumbs down, null = no feedback yet.
                $table->tinyInteger('feedback')->nullable();
                $table->timestamp('feedback_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['matched', 'created_at'], 'ai_bot_interactions_matched_idx');
                $table->index('created_at', 'ai_bot_interactions_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_interactions');
    }
};
