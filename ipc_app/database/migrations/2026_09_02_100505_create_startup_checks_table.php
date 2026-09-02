<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            // Checklist items — vocabulary per item verified against the real Power Apps export
            // 2026-09-02 (ipc_app/app_legacy/, References/DataSources.json + Controls/4.json).
            // See StartupCheck::checklistGroups() for the exact option set per field.
            $table->string('product_standard_status')->nullable();
            $table->string('sample_challenge_test_status')->nullable();
            $table->string('wi_im_match_status')->nullable();
            $table->string('pm_bom_match_status')->nullable(); // Match With BOM / Not Match With BOM
            $table->string('bulk_status_status')->nullable(); // Bulk Release / Bulk Not Yet Release
            $table->string('machine_vision_status')->nullable(); // Available / Not Available
            $table->string('machine_weigher_status')->nullable(); // Available / Not Available
            $table->string('machine_roller_status')->nullable(); // Available / Not Available
            $table->string('machine_load_cell_status')->nullable(); // Available / Not Available
            $table->string('machine_balance_status')->nullable(); // Available / Not Available
            $table->string('validation_report_status')->nullable(); // real SharePoint Choice dropdown, values unresolvable from export — plain required text for now
            $table->string('identity_line_board_status')->nullable(); // Complete / Not Yet Complete
            $table->string('scan_bpom_status')->nullable(); // Conform / Not Conform — legacy SACN_NUMBER_NA_WHTH_BPOM_MOBILE
            $table->string('sample_30pcs_appearance_status')->nullable(); // Conform / Not Conform
            $table->string('sample_30pcs_vacuum_status')->nullable(); // Conform / Not Conform
            $table->string('functional_test_status')->nullable(); // Conform / Not Conform — 5 PCS sample
            $table->string('standard_weight_masterbox_status')->nullable(); // Conform / Not Conform

            $table->decimal('filling_range_min', 10, 2)->nullable();
            $table->decimal('filling_range_max', 10, 2)->nullable();
            $table->decimal('density', 10, 4)->nullable();
            $table->string('heating')->nullable();

            $table->string('line_leader_name')->nullable();
            $table->string('operator_name')->nullable();

            // NOTE: legacy IM_NUMBER/COLOR/CODING/TEMPERATURE_SETTING are pure photo fields
            // (LargeImage in the real SharePoint schema, no text value) — route through
            // ipc_attachments (field_label) instead of a text column here, once photo upload
            // is built for this stage.
            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_checks');
    }
};
