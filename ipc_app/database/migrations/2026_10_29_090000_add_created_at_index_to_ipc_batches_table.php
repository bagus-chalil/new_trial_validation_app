<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Created-date range in the list filter bar (Batch, Micro Check, Approval Queue). Additive
// only; guarded like the other list-performance indexes so a re-run is a no-op.
return new class extends Migration
{
    private const INDEX = 'ipc_batches_created_at_index';

    public function up(): void
    {
        if (! Schema::hasIndex('ipc_batches', self::INDEX)) {
            Schema::table('ipc_batches', fn (Blueprint $table) => $table->index('created_at', self::INDEX));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('ipc_batches', self::INDEX)) {
            Schema::table('ipc_batches', fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }
    }
};
