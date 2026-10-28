<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VALUE_COLUMNS = [
        'total_plate_count_result', 'total_plate_count_std',
        'yeast_mold_result', 'yeast_mold_std',
        'pathogen_result', 'pathogen_std',
    ];

    public function up(): void
    {
        // Micro Check now has "Simpan Progress" (partial values allowed) and "Selesaikan" (all six
        // values, locks the record and sends the batch to Approval) — so the values go nullable
        // and the record gets its own completed_at, same shape as the other stages.
        Schema::table('micro_checks', function (Blueprint $table) {
            foreach (self::VALUE_COLUMNS as $column) {
                $table->string($column, 50)->nullable()->change();
            }
            $table->timestamp('completed_at')->nullable()->after('save_count');
        });

        Schema::table('micro_check_revisions', function (Blueprint $table) {
            foreach (self::VALUE_COLUMNS as $column) {
                $table->string($column, 50)->nullable()->change();
            }
            $table->boolean('finalize')->default(false)->after('revision_no');
        });

        // Under the old rule a batch could only finalize Finished Good once Micro Check was
        // complete and was then already in Approval — mark those Micro Checks as finalized so
        // they stay locked and nothing already sent to Approval changes.
        DB::table('micro_checks')
            ->whereIn('ipc_batch_id', DB::table('finished_checks')->whereNotNull('completed_at')->select('ipc_batch_id'))
            ->whereNotNull('total_plate_count_result')->whereNotNull('total_plate_count_std')
            ->whereNotNull('yeast_mold_result')->whereNotNull('yeast_mold_std')
            ->whereNotNull('pathogen_result')->whereNotNull('pathogen_std')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('micro_check_revisions', function (Blueprint $table) {
            $table->dropColumn('finalize');
        });

        Schema::table('micro_checks', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
