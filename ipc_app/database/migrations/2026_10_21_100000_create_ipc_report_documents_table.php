<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dynamic doc number/revision/title for the IPC report PDFs, so QAC staff can update them when
 * the real paper forms are revised without a code change. One row per report_key; report_key
 * values match the 3 real paper forms in docs/report/ (see CLAUDE.md's "per-flow report output"
 * plan): 'filling' (FR.QAC.192.02), 'packing' (FR.QAC.182.03), 'filling_packing_fg'
 * (FR.QAC.193.00). Seeded here with the current real values so BuildsIpcReportPayloads has
 * something to read from day one; the admin CRUD screen (IpcReportDocumentController) is how
 * staff edit them afterwards.
 */
return new class extends Migration
{
    private const SEED = [
        ['report_key' => 'filling', 'doc_number' => 'FR.QAC.192.02', 'revision' => '02', 'title' => 'Form Filling'],
        ['report_key' => 'packing', 'doc_number' => 'FR.QAC.182.03', 'revision' => '03', 'title' => 'Form Packing'],
        ['report_key' => 'filling_packing_fg', 'doc_number' => 'FR.QAC.193.00', 'revision' => '0', 'title' => 'Laporan Pemeriksaan In Process Control Filling & Packing'],
    ];

    public function up(): void
    {
        Schema::create('ipc_report_documents', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 50)->unique();
            $table->string('doc_number', 50);
            $table->string('revision', 20);
            $table->string('title', 255);
            $table->date('effective_date')->nullable();
            $table->timestamps();
        });

        foreach (self::SEED as $row) {
            DB::table('ipc_report_documents')->insert([
                ...$row,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ipc_report_documents');
    }
};
