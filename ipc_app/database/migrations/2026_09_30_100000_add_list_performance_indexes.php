<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indexes for the list/search/count queries so they stay fast at ~1M batches and ~10k
// products (tablets on the floor). Each is guarded so re-running on a DB that already has
// one is a no-op.
return new class extends Migration
{
    private const INDEXES = [
        'ipc_batches' => [
            // stage filter + soft-delete scope + latest('id') ordering (batch list, dashboard,
            // approval queue) — an ordered index range scan instead of a filesort.
            'ipc_batches_stage_deleted_id_index' => ['current_stage', 'deleted_at', 'id'],
            // "completed today" count on the dashboard. deleted_at sits in the middle on
            // purpose: every query carries the soft-delete `deleted_at IS NULL`, and without it
            // here MySQL picked this index for plain stage counts and then did ~1M row lookups
            // just to check deleted_at (2.7s vs 0.3s, measured on 1M batches).
            'ipc_batches_stage_deleted_updated_index' => ['current_stage', 'deleted_at', 'updated_at'],
            // no_batch prefix search.
            'ipc_batches_no_batch_index' => ['no_batch'],
            // Product-name side of the batch search: index-only count/order of a product's
            // live batches (a broad term like "cushion" matches ~1/3 of all batches; 0.95s →
            // 0.08s count on 1M rows vs the bare FK index).
            'ipc_batches_product_deleted_id_index' => ['master_product_id', 'deleted_at', 'id'],
            // Deliberately NO standalone deleted_at index: ~all rows are NULL, the optimizer
            // badly under-estimates that and chose it for every soft-delete-scoped count
            // (2s dashboard). The Recycle Bin's `deleted_at IS NOT NULL` is served by a skip
            // scan over the composite index above instead (~1ms on 1M rows).
        ],
        'master_products' => [
            // FG Code picker (active only) and master list ordering.
            'master_products_active_fg_code_index' => ['is_active', 'fg_code'],
            'master_products_product_name_index' => ['product_name'],
        ],
        'master_product_bulk_codes' => [
            'master_product_bulk_codes_bulk_code_index' => ['bulk_code'],
        ],
        'ipc_approvals' => [
            // Approval Queue candidate narrowing (decision != Approved).
            'ipc_approvals_decision_batch_index' => ['decision', 'ipc_batch_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
