<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasterProductBulkCodeRequest;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MasterProductBulkCodeController extends Controller
{
    public function store(StoreMasterProductBulkCodeRequest $request, MasterProduct $masterProduct): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $bulkCode = MasterProductBulkCode::findOrNew($data['id'] ?? null);
        $bulkCode->master_product_id = $masterProduct->id;
        $bulkCode->fill($data);
        $bulkCode->save();

        return back()->with('success', $data['id'] ?? null ? 'Bulk code diperbarui.' : 'Bulk code ditambahkan.');
    }

    public function destroy(MasterProduct $masterProduct, MasterProductBulkCode $bulkCode, Request $request): RedirectResponse
    {
        abort_unless($bulkCode->master_product_id === $masterProduct->id, 404);

        $bulkCode->deleted_by = $request->user()->id;
        $bulkCode->save();
        $bulkCode->delete();

        return back()->with('success', 'Bulk code dihapus.');
    }
}
