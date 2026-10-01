<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One FG batch can be filled from several bulk codes. `ipc_batches.bulk_code` stays as the
 * display snapshot (now the comma-joined list, hence widened to text) and
 * `master_product_bulk_code_id` keeps pointing at the first one; this table holds the full set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipc_batch_bulk_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('master_product_bulk_code_id')->nullable()->constrained('master_product_bulk_codes')->nullOnDelete();
            $table->string('bulk_code');
            $table->timestamps();

            $table->unique(['ipc_batch_id', 'master_product_bulk_code_id'], 'ipc_batch_bulk_codes_batch_bulk_unique');
        });

        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->text('bulk_code')->nullable()->change();
        });

        // Backfill existing single-bulk-code batches.
        $now = now();
        DB::table('ipc_batches')
            ->whereNotNull('bulk_code')
            ->orderBy('id')
            ->get(['id', 'master_product_bulk_code_id', 'bulk_code'])
            ->chunk(500)
            ->each(fn ($rows) => DB::table('ipc_batch_bulk_codes')->insert($rows->map(fn ($row) => [
                'ipc_batch_id' => $row->id,
                'master_product_bulk_code_id' => $row->master_product_bulk_code_id,
                'bulk_code' => $row->bulk_code,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_batch_bulk_codes');

        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->string('bulk_code')->nullable()->change();
        });
    }
};
