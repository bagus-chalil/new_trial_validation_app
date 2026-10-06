<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            // Chosen once at batch creation (see IpcBatchController::create/store): which stages
            // this batch goes through. Defaults to 'full' (today's only behavior — Startup →
            // Filling → Packing → Finished → Approval → Print) so every existing/seeded batch is
            // unaffected. See IpcBatch::FLOW_TYPES / activeStages() for the full flow→stage map.
            $table->string('flow_type')->default('full')->after('current_stage');
        });
    }

    public function down(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->dropColumn('flow_type');
        });
    }
};
