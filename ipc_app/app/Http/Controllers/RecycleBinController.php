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
    /**
     * Deleted rows only ever accumulate, so each section shows the most recent LIMIT items
     * (plus the real total) instead of loading every trashed row onto the tablet.
     */
    private const LIMIT = 50;

    public function index(): Response
    {
        return Inertia::render('recycle-bin/index', [
            'counts' => [
                'batches' => IpcBatch::onlyTrashed()->count(),
                'products' => MasterProduct::onlyTrashed()->count(),
                'lines' => MasterLine::onlyTrashed()->count(),
                'testTypes' => MasterTestType::onlyTrashed()->count(),
            ],

            // Two steps on purpose: picking the newest ids alone is an index-only skip scan
            // (~1ms on 1M batches); asking for the other columns in the same query made MySQL
            // full-scan + filesort instead (~320ms).
            'batches' => IpcBatch::onlyTrashed()
                ->whereIn('id', IpcBatch::onlyTrashed()->latest('deleted_at')->limit(self::LIMIT)->pluck('id'))
                ->with(['masterProduct:id,product_name,fg_code', 'masterLine:id,name', 'deletedByUser:id,name'])
                ->latest('deleted_at')
                ->limit(self::LIMIT)
                ->get(['id', 'no_batch', 'master_product_id', 'master_line_id', 'current_stage', 'deleted_at', 'deleted_by']),

            'products' => MasterProduct::onlyTrashed()
                ->with('deletedByUser:id,name')
                ->latest('deleted_at')
                ->limit(self::LIMIT)
                ->get(['id', 'fg_code', 'product_name', 'deleted_at', 'deleted_by']),

            'lines' => MasterLine::onlyTrashed()
                ->with('deletedByUser:id,name')
                ->latest('deleted_at')
                ->limit(self::LIMIT)
                ->get(['id', 'code', 'name', 'category', 'area', 'deleted_at', 'deleted_by']),

            'testTypes' => MasterTestType::onlyTrashed()
                ->with('deletedByUser:id,name')
                ->latest('deleted_at')
                ->limit(self::LIMIT)
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
