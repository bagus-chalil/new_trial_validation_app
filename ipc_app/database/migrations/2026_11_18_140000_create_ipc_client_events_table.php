<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field-issue log: JS errors, failed/slow requests, camera/upload failures and operator reports
     * sent from the browser, plus unhandled server exceptions. Brand-new table (additive only).
     * "Resolved" is a timestamp on the rows of one fingerprint; a later event with the same
     * fingerprint has resolved_at = null, so a fixed issue that comes back reappears as open.
     */
    public function up(): void
    {
        if (Schema::hasTable('ipc_client_events')) {
            return;
        }

        Schema::create('ipc_client_events', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 40);
            $table->string('kind', 30);
            $table->string('message', 500);
            $table->text('detail')->nullable();
            $table->string('url', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('ipc_batch_id')->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('device', 60)->nullable();
            $table->boolean('online')->nullable();
            $table->string('net_type', 20)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['fingerprint', 'occurred_at'], 'ipc_client_events_fp_idx');
            $table->index(['resolved_at', 'occurred_at'], 'ipc_client_events_open_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_client_events');
    }
};
