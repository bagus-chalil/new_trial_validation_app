<?php

namespace App\Http\Controllers;

use App\Exports\MasterProductsTemplateExport;
use App\Http\Requests\StoreMasterProductRequest;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterProductController extends Controller
{
    public function index(Request $request): Response
    {
        // The chip counts follow the search, so they are built from the same base query.
        $searched = MasterProduct::query()
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('product_name', 'like', "%{$q}%")
                        ->orWhere('fg_code', 'like', "%{$q}%")
                        ->orWhereIn('id', MasterProductBulkCode::query()
                            ->select('master_product_id')
                            ->where('bulk_code', 'like', "%{$q}%"));
                });
            });

        $products = (clone $searched)
            ->withCount('bulkCodes')
            ->with(['bulkCodes' => fn ($query) => $query->orderBy('bulk_code')])
            ->when($request->string('bulk')->toString(), fn ($query, $bulk) => match ($bulk) {
                'with' => $query->has('bulkCodes'),
                'without' => $query->doesntHave('bulkCodes'),
                default => $query,
            })
            ->orderBy('product_name')
            ->paginate(20)
            ->withQueryString();

        $total = (clone $searched)->count();
        $withBulkCodes = (clone $searched)->has('bulkCodes')->count();

        return Inertia::render('masters/products/index', [
            'products' => $products,
            'filters' => $request->only('q', 'bulk'),
            'bulkCodeCounts' => [
                'all' => $total,
                'with' => $withBulkCodes,
                'without' => $total - $withBulkCodes,
            ],
        ]);
    }

    public function store(StoreMasterProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = MasterProduct::findOrNew($data['id'] ?? null);
        $product->fill($data);
        $product->save();

        return back()->with('success', $data['id'] ?? null ? 'Produk diperbarui.' : 'Produk ditambahkan.');
    }

    public function destroy(MasterProduct $masterProduct, Request $request): RedirectResponse
    {
        $masterProduct->deleted_by = $request->user()->id;
        $masterProduct->save();
        $masterProduct->delete();

        return back()->with('success', 'Produk dihapus.');
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new MasterProductsTemplateExport, 'template-master-produk.xlsx');
    }
}
