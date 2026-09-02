<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIpcBatchRequest;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IpcBatchController extends Controller
{
    public function index(Request $request): Response
    {
        $batches = IpcBatch::query()
            ->with(['masterProduct', 'masterLine', 'creator', 'startupCheck'])
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('no_batch', 'like', "%{$q}%")
                        ->orWhereHas('masterProduct', fn ($query) => $query->where('product_name', 'like', "%{$q}%"));
                });
            })
            ->when($request->string('stage')->toString(), fn ($query, $stage) => $query->where('current_stage', $stage))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('batches/index', [
            'batches' => $batches,
            'filters' => $request->only(['q', 'stage']),
            'stages' => IpcBatch::STAGES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('batches/create', [
            'products' => MasterProduct::query()->where('is_active', true)->orderBy('product_name')->get(['id', 'fg_code', 'product_name', 'bulk_code']),
            'lines' => MasterLine::query()->where('is_active', true)->orderBy('name')->get(['id', 'category', 'area', 'code', 'name']),
        ]);
    }

    public function store(StoreIpcBatchRequest $request): RedirectResponse
    {
        $batch = IpcBatch::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);

        return redirect()->route('startup-check.edit', $batch)->with('success', 'Batch dibuat. Lanjutkan ke Startup Check.');
    }
}
