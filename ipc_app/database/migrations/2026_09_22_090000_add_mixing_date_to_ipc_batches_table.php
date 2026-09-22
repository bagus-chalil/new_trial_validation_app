<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->date('mixing_date')->nullable()->after('no_batch');
        });
    }

    public function down(): void
    {
        Schema::table('ipc_batches', function (Blueprint $table) {
            $table->dropColumn('mixing_date');
        });
    }
};
