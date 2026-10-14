<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Output of the self-learning job (`ai:learn`). Brand-new Laravel-only
     * tables; nothing in the shared legacy schema is altered.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_lessons')) {
            Schema::create('ai_lessons', function (Blueprint $table) {
                $table->increments('id');
                $table->string('kind', 40);
                $table->string('scope_key', 200);
                $table->json('payload');
                $table->unsignedInteger('sample_size')->default(0);
                $table->string('confidence', 10);
                $table->string('status', 12)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('learned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();

                $table->unique(['kind', 'scope_key'], 'ai_lessons_unique');
                $table->index(['kind', 'status']);
            });
        }

        if (! Schema::hasTable('ai_learning_runs')) {
            Schema::create('ai_learning_runs', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('created')->default(0);
                $table->unsignedInteger('updated')->default(0);
                $table->unsignedInteger('unchanged')->default(0);
                $table->unsignedInteger('revoked')->default(0);
                $table->unsignedInteger('insufficient')->default(0);
                $table->unsignedInteger('excluded_trials')->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamp('ran_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learning_runs');
        Schema::dropIfExists('ai_lessons');
    }
};
