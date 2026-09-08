<?php

namespace App\Http\Controllers;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterTestType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecycleBinController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('recycle-bin/index', [
            'batches' => IpcBatch::onlyTrashed()
                ->with(['masterProduct', 'masterLine', 'deletedByUser'])
                ->latest('deleted_at')
                ->get(['id', 'no_batch', 'master_product_id', 'master_line_id', 'current_stage', 'deleted_at', 'deleted_by']),

            'products' => MasterProduct::onlyTrashed()
                ->with('deletedByUser')
                ->latest('deleted_at')
                ->get(['id', 'fg_code', 'product_name', 'deleted_at', 'deleted_by']),

            'lines' => MasterLine::onlyTrashed()
                ->with('deletedByUser')
                ->latest('deleted_at')
                ->get(['id', 'code', 'name', 'category', 'area', 'deleted_at', 'deleted_by']),

            'testTypes' => MasterTestType::onlyTrashed()
                ->with('deletedByUser')
                ->latest('deleted_at')
                ->get(['id', 'name', 'category', 'deleted_at', 'deleted_by']),
        ]);
    }

    public function restoreBatch(int $id): RedirectResponse
    {
        IpcBatch::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Batch berhasil dikembalikan.');
    }

    public function restoreProduct(int $id): RedirectResponse
    {
        MasterProduct::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Produk berhasil dikembalikan.');
    }

    public function restoreLine(int $id): RedirectResponse
    {
        MasterLine::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Line berhasil dikembalikan.');
    }

    public function restoreTestType(int $id): RedirectResponse
    {
        MasterTestType::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Test type berhasil dikembalikan.');
    }
}
