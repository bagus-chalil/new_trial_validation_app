<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasterProductRequest;
use App\Models\MasterProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = MasterProduct::query()
            ->withCount('bulkCodes')
            ->with(['bulkCodes' => fn ($query) => $query->orderBy('bulk_code')])
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('product_name', 'like', "%{$q}%")
                        ->orWhere('fg_code', 'like', "%{$q}%")
                        ->orWhereHas('bulkCodes', fn ($query) => $query->where('bulk_code', 'like', "%{$q}%"));
                });
            })
            ->orderBy('product_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('masters/products/index', [
            'products' => $products,
            'filters' => $request->only('q'),
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
}
