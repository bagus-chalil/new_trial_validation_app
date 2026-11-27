<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference to the Machine Master owned by new_trial_validation_app
 * (mc_lines.line_code, e.g. SC-01). Plain string, no foreign key: the master
 * lives in another application's database and is read-only from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('master_lines', 'machine_line_code')) {
            Schema::table('master_lines', function (Blueprint $table) {
                $table->string('machine_line_code', 30)->nullable()->index()->after('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_lines', 'machine_line_code')) {
            Schema::table('master_lines', function (Blueprint $table) {
                $table->dropColumn('machine_line_code');
            });
        }
    }
};
