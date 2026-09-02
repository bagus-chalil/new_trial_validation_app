<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finished_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipc_batch_id')->unique()->constrained('ipc_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            // NOTE: legacy WI_NUMBER/EXP_DATE are pure photo fields (LargeImage in the real
            // SharePoint schema, no text/date value) — route through ipc_attachments
            // (field_label 'wi_number'/'exp_date') instead of dedicated columns here.
            $table->decimal('quantity_wi', 12, 2)->nullable();
            $table->decimal('masterbox', 12, 2)->nullable();
            $table->decimal('no_pallet_qty', 12, 2)->nullable();
            $table->unsignedInteger('quantity_sampling_aql')->nullable();
            $table->unsignedInteger('quantity_sample_aql_cd')->nullable();
            $table->unsignedInteger('quantity_sample_aql_md')->nullable();
            $table->unsignedInteger('quantity_sample_aql_mnd')->nullable();
            $table->unsignedInteger('quantity_special_inspection')->nullable();
            $table->unsignedInteger('quantity_special_inspection_cd')->nullable();
            $table->unsignedInteger('quantity_special_inspection_md')->nullable();
            $table->unsignedInteger('quantity_special_inspection_mnd')->nullable();
            $table->string('line_leader_name')->nullable();

            $table->string('disposition')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_checks');
    }
};
