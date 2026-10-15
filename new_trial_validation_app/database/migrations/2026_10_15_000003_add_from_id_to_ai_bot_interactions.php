<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which earlier (unanswered) interaction a question came from when
     * the user clicked a "did you mean" suggestion. A clicked suggestion is a
     * stronger teaching signal than text similarity.
     *
     * Additive and nullable; hasColumn keeps it idempotent.
     */
    public function up(): void
    {
        if (Schema::hasTable('ai_bot_interactions') && ! Schema::hasColumn('ai_bot_interactions', 'from_id')) {
            Schema::table('ai_bot_interactions', function (Blueprint $table) {
                $table->unsignedInteger('from_id')->nullable()->after('feedback_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_bot_interactions', 'from_id')) {
            Schema::table('ai_bot_interactions', function (Blueprint $table) {
                $table->dropColumn('from_id');
            });
        }
    }
};
