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
        // New table, no legacy counterpart — the "Line Configuration Report"
        // (FR.QSE-style production line-config sheet, previously only kept
        // as an ad-hoc Excel file) is a feature this app introduces. Still
        // guarded the same way as every other Fase 1/3 table, since the
        // shared MySQL DB is touched by multiple sessions/environments and
        // may already have this table from an earlier migrate run.
        if (! Schema::hasTable('trial_line_configuration_reports')) {
            Schema::create('trial_line_configuration_reports', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('trial_id')->unique();
                $table->date('report_date')->nullable();
                $table->string('client_name', 150)->nullable();
                $table->string('pic', 150)->nullable();
                $table->string('operator', 150)->nullable();
                $table->string('validation_name', 150)->nullable();
                // Plain free text, not numbers — the real Excel source this
                // was ported from (see the 2026-09-16 feature request) often
                // leaves these as a placeholder label like "General Pcs", and
                // NG is always written as a combined "10 Pcs (14%)" string,
                // never a bare integer.
                $table->string('total_qty', 50)->nullable();
                $table->string('setting_qty', 50)->nullable();
                $table->string('pass_qty', 50)->nullable();
                $table->string('ng_qty', 50)->nullable();
                $table->string('capacity_label', 50)->nullable();
                $table->json('production_standard')->nullable();
                $table->json('line_configuration')->nullable();
                $table->text('opinion')->nullable();
                $table->unsignedInteger('updated_by_user_id')->nullable();
                $table->timestamps();

                // No DB-level FK to trials_header: same convention as every
                // other trial-child table in this app (trials_review,
                // trials_weighing, trial_attachment_files, ...) — none of
                // them declare one either. Also avoids a real signed/unsigned
                // int mismatch: the shared trials_header.id is a plain
                // (signed) `int`, not `int unsigned`, which MySQL 8 rejects
                // as an incompatible FK reference type (hit and confirmed
                // while writing this migration).
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trial_line_configuration_reports');
    }
};
