<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 5 (alignment with the QAC SuperApp brief):
 * classification axes per Case and the verified FG mapping (iERP import).
 * Additive only: new `vd_*` tables, hasTable-guarded, no foreign keys,
 * nothing altered on shared tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vd_case_classifications')) {
            Schema::create('vd_case_classifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('case_id')->unique();
                // Mixing, Filling-Packing
                $table->string('process_area', 20)->nullable();
                // NPD, Improvement, Other
                $table->string('purpose', 20)->nullable();
                // Product, Machine, RM, PM, Process
                $table->string('subject', 20)->nullable();
                // Validation Plan, Trial Report, Line Configuration
                $table->string('document_type', 30)->nullable();
                $table->string('other_purpose_note', 255)->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vd_fg_mappings')) {
            Schema::create('vd_fg_mappings', function (Blueprint $table) {
                $table->id();
                // Official FG code from iERP; unique, history is never overwritten
                // (a changed name is kept in item_name_previous).
                $table->string('fg_code', 40)->unique();
                $table->string('item_name', 255);
                $table->string('item_name_previous', 255)->nullable();
                $table->date('effective_date')->nullable();
                // import, manual
                $table->string('source', 20)->default('import');
                $table->dateTime('imported_at')->nullable();
                // The products master row this code was matched to (null = unmatched).
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->dateTime('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by_user_id')->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: v2.0 tables are never dropped from a migration.
    }
};
