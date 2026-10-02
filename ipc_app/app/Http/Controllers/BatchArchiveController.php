<?php

namespace App\Http\Controllers;

use App\Models\IpcBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Archive for finished batches: moves them out of the active Batch list without deleting
 * anything. Archived batches stay fully viewable/printable (and their verification QR keeps
 * working) — only the Batch list hides them.
 */
class BatchArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim($request->string('q')->toString());
        $perPage = 20;

        $query = IpcBatch::query()->with([
            'masterProduct:id,fg_code,product_name',
            'masterLine:id,name,code',
            'creator:id,name',
            'archivedByUser:id,name',
        ]);

        if ($q === '') {
            $batches = $query->archived()->latest('archived_at')->latest('id')->paginate($perPage)->withQueryString();
        } else {
            // Same index-driven search as the Batch list (see IpcBatch::searchBranches).
            $page = LengthAwarePaginator::resolveCurrentPage();
            $ids = IpcBatch::searchIds($q, null, $perPage, ($page - 1) * $perPage, archived: true);
            $items = $query->whereIn('id', $ids)->latest('id')->get();

            $batches = (new LengthAwarePaginator($items, IpcBatch::searchCount($q, null, archived: true), $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]))->withQueryString();
        }

        return Inertia::render('archive/index', [
            'batches' => $batches,
            'filters' => $request->only(['q']),
        ]);
    }

    public function store(Request $request, IpcBatch $batch): RedirectResponse
    {
        if (! $batch->isArchivable()) {
            throw ValidationException::withMessages([
                'archive' => 'Hanya batch yang sudah Selesai yang bisa diarsipkan.',
            ]);
        }

        $batch->forceFill(['archived_at' => now(), 'archived_by' => $request->user()->id])->save();

        return back()->with('success', "Batch {$batch->no_batch} dipindahkan ke Arsip.");
    }

    public function destroy(IpcBatch $batch): RedirectResponse
    {
        $batch->forceFill(['archived_at' => null, 'archived_by' => null])->save();

        return back()->with('success', "Batch {$batch->no_batch} dikembalikan ke daftar Batch.");
    }
}
