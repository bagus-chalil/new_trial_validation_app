<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // QC records several master box counts ("4,17,55,78") and pallet/qty pairs ("1/2016")
        // per batch, which a decimal column can't hold. Existing decimal values convert to their
        // plain text form (e.g. "50.00").
        foreach (['finished_checks', 'finished_check_revisions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('masterbox')->nullable()->change();
                $table->string('no_pallet_qty')->nullable()->change();
            });

            // Drop the meaningless ".00" the decimal column used to add ("50.00" -> "50").
            foreach (['masterbox', 'no_pallet_qty'] as $column) {
                DB::table($tableName)->where($column, 'like', '%.00')
                    ->update([$column => DB::raw("SUBSTRING({$column}, 1, CHAR_LENGTH({$column}) - 3)")]);
            }
        }
    }

    public function down(): void
    {
        foreach (['finished_checks', 'finished_check_revisions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('masterbox', 12, 2)->nullable()->change();
                $table->decimal('no_pallet_qty', 12, 2)->nullable()->change();
            });
        }
    }
};
