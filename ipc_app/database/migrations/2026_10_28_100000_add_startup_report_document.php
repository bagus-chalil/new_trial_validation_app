<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Flow Packing + FG prints Startup / Packing / Finished as 3 forms; Startup is numbered
 * FR.QAC.182.03 and gets its own editable ipc_report_documents row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ipc_report_documents')->where('report_key', 'startup')->exists()) {
            return;
        }

        DB::table('ipc_report_documents')->insert([
            'report_key' => 'startup',
            'doc_number' => 'FR.QAC.182.03',
            'revision' => '03',
            'title' => 'Form Start Up',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ipc_report_documents')->where('report_key', 'startup')->delete();
    }
};
