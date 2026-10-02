<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Data-only, one-off: runs trials:repair-review-aliases --apply once on
 * deploy, closing Pending "PRD" review rows left next to an already-Reviewed
 * "PROD" row (trials submitted from the legacy app, which offered both
 * codes). Idempotent — a no-op when nothing is stuck, e.g. on a fresh DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('trials:repair-review-aliases', ['--apply' => true]);
    }

    public function down(): void
    {
        // Closed review rows are not reopened.
    }
};
