<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Small key/value store for app-wide settings that have no table of their own, e.g. the
     * token of the public (no-login) Monitor Line board. Additive only.
     */
    public function up(): void
    {
        Schema::create('ipc_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_settings');
    }
};
