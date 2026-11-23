<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            // "No instruksi mutu" printed on the IM document. Until now only its photo was kept; the
            // number is now typed (or taken from the AI suggestion) and required on Startup Check.
            // Nullable only because older, already finished checks have none.
            $table->string('im_document_number', 150)->nullable()->after('wi_im_match_status');
        });
    }

    public function down(): void
    {
        Schema::table('startup_checks', function (Blueprint $table) {
            $table->dropColumn('im_document_number');
        });
    }
};
