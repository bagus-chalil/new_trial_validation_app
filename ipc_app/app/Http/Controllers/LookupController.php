<?php

namespace App\Http\Controllers;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Small, capped JSON search endpoints backing the searchable selects / batch sidebar.
 * Pages used to embed the entire master (8000+ products with their bulk codes) as an Inertia
 * prop and render every row into a dropdown, which froze tablets. Now the page sends nothing
 * up front and the client asks for at most LIMIT matches as the user types.
 */
class LookupController extends Controller
{
    private const LIMIT = 30;

    public function products(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        $products = MasterProduct::query()
            ->where('is_active', true)
            // Every word may appear anywhere in FG code or name; a contains match over a table
            // this small (~10k rows) stays cheap, and LIMIT caps what is sent back.
            ->when($q !== '', fn ($query) => SearchTerm::apply($query, $q, ['fg_code', 'product_name']))
            ->with(['bulkCodes' => fn ($query) => $query->where('is_active', true)->orderBy('bulk_code')->select(['id', 'master_product_id', 'bulk_code'])])
            ->orderBy('fg_code')
            ->limit(self::LIMIT)
            ->get(['id', 'fg_code', 'product_name', 'shelf_life_months']);

        return response()->json($products);
    }

    public function batches(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        $batches = IpcBatch::query()
            ->when(! $request->user()->isStaff(), fn ($query) => $query->visibleTo($request->user()))
            // Index-driven search (see IpcBatch::searchBranches) — a plain OR query here
            // full-scanned ~1M batches whenever the search had no hits.
            ->when($q !== '', fn ($query) => $query->whereIn('id', IpcBatch::searchIds($q, null, self::LIMIT)))
            ->with(['masterProduct:id,product_name,fg_code', 'masterLine:id,name'])
            ->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'no_batch', 'current_stage', 'flow_type', 'master_product_id', 'master_line_id']);

        return response()->json($batches);
    }
}
