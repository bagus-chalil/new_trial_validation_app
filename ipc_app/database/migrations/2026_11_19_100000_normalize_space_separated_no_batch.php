<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data only. New batches must be written "26Y034/JLB"; some older ones were typed with a space
 * ("26X004 DKB", "26S009  JEB"). Only that exact pattern (code, spaces, 3 letters) is rewritten
 * with a slash. Anything else (missing letters, "26X1234", reversed order, ...) is left untouched
 * on purpose. Only no_batch changes (updated_at is not touched); the old value of every changed
 * row is kept in ipc_no_batch_normalized so down() can restore it. Drop that table by hand once
 * verified.
 */
return new class extends Migration
{
    private const PATTERN = '/^(\d{2}[A-Z]\d{3})[ ]+([A-Z]{3})$/';

    public function up(): void
    {
        Schema::create('ipc_no_batch_normalized', function ($table) {
            $table->unsignedBigInteger('ipc_batch_id')->primary();
            $table->string('old_no_batch');
            $table->string('new_no_batch');
            $table->timestamp('normalized_at')->useCurrent();
        });

        DB::table('ipc_batches')->select(['id', 'no_batch'])->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                if (! preg_match(self::PATTERN, $row->no_batch, $m)) {
                    continue;
                }

                $new = $m[1].'/'.$m[2];
                DB::table('ipc_no_batch_normalized')->insert(['ipc_batch_id' => $row->id, 'old_no_batch' => $row->no_batch, 'new_no_batch' => $new]);
                DB::table('ipc_batches')->where('id', $row->id)->update(['no_batch' => $new]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ipc_no_batch_normalized')) {
            return;
        }

        foreach (DB::table('ipc_no_batch_normalized')->get() as $row) {
            // Only restore a row nobody has edited since.
            DB::table('ipc_batches')->where('id', $row->ipc_batch_id)->where('no_batch', $row->new_no_batch)->update(['no_batch' => $row->old_no_batch]);
        }

        Schema::dropIfExists('ipc_no_batch_normalized');
    }
};
