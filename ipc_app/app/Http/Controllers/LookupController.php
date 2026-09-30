<?php

namespace App\Http\Controllers;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
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
            ->when($q !== '', function ($query) use ($q) {
                $like = $this->escapeLike($q);
                $query->where(function ($query) use ($like) {
                    // fg_code prefix match can use the unique index; name is a contains match
                    // over a table that's small enough (~10k rows) for that to stay cheap.
                    $query->where('fg_code', 'like', "{$like}%")
                        ->orWhere('product_name', 'like', "%{$like}%");
                });
            })
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
            // Index-driven search (see IpcBatch::searchBranches) — a plain OR query here
            // full-scanned ~1M batches whenever the search had no hits.
            ->when($q !== '', fn ($query) => $query->whereIn('id', IpcBatch::searchIds($q, null, self::LIMIT)))
            ->with(['masterProduct:id,product_name,fg_code', 'masterLine:id,name'])
            ->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'no_batch', 'current_stage', 'master_product_id', 'master_line_id']);

        return response()->json($batches);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
