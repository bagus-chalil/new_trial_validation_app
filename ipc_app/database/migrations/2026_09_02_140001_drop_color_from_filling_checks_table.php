<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filling_checks', function (Blueprint $table) {
            // Confirmed against the real export (Controls/625.json): COLOR is bound to
            // Image10.Image (a camera control) and the real SharePoint column is LargeImage,
            // not text — same class of mistake already fixed on startup_checks' im_number/
            // coding/temperature_setting. Route through ipc_attachments once photo upload
            // exists for this stage; dropped here rather than left unused.
            $table->dropColumn('color');
        });
    }

    public function down(): void
    {
        Schema::table('filling_checks', function (Blueprint $table) {
            $table->string('color')->nullable();
        });
    }
};
