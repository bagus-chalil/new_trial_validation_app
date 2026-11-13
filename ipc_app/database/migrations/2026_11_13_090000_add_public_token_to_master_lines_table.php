<?php

use App\Models\MasterLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Unguessable per-line token for the public "Status Line" QR page (/line/{token}). Additive:
     * a nullable unique column, backfilled for the lines that already exist.
     */
    public function up(): void
    {
        Schema::table('master_lines', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique()->after('name');
        });

        MasterLine::withTrashed()->whereNull('public_token')->each(function (MasterLine $line) {
            $line->forceFill(['public_token' => Str::random(32)])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('master_lines', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
