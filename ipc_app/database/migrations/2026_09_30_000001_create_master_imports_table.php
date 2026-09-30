<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Two-phase master-data spreadsheet imports: a queued validation pass (writes nothing to the
// master tables, only these rows) produces a preview + per-row issues; the user then confirms
// and a queued commit pass applies the valid rows inside a single DB transaction.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('status', 20)->default('queued');
            $table->boolean('cancel_requested')->default(false);
            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->json('preview')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['user_id', 'type']);
        });

        Schema::create('master_import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('severity', 10);
            $table->string('message', 500);
            $table->json('values')->nullable();

            $table->index(['master_import_id', 'severity', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_import_issues');
        Schema::dropIfExists('master_imports');
    }
};
