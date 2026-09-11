<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trials_review', function (Blueprint $table) {
            $table->unsignedTinyInteger('edit_count')->default(0)->after('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trials_review', function (Blueprint $table) {
            $table->dropColumn('edit_count');
        });
    }
};
