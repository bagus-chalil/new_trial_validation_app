<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIpcBatchRequest;
use App\Models\IpcBatch;
use App\Models\MasterProductBulkCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class IpcBatchController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim($request->string('q')->toString());
        $stage = $request->string('stage')->toString() ?: null;
        $perPage = 20;

        $query = IpcBatch::query()->with([
            'masterProduct:id,fg_code,product_name',
            'masterLine:id,name,code',
            'creator:id,name',
            'startupCheck:id,ipc_batch_id,completed_at',
            'fillingCheck:id,ipc_batch_id,completed_at',
        ]);

        if ($q === '') {
            $batches = $query
                ->when($stage, fn ($query) => $query->where('current_stage', $stage))
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();
        } else {
            // Searched: page ids and total come from IpcBatch's index-driven search helpers
            // (see IpcBatch::searchBranches for why a plain OR query can't be used at 1M rows).
            $page = LengthAwarePaginator::resolveCurrentPage();
            $ids = IpcBatch::searchIds($q, $stage, $perPage, ($page - 1) * $perPage);
            $items = $query->whereIn('id', $ids)->latest('id')->get();

            $batches = (new LengthAwarePaginator($items, IpcBatch::searchCount($q, $stage), $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]))->withQueryString();
        }

        return Inertia::render('batches/index', [
            'batches' => $batches,
            'filters' => $request->only(['q', 'stage']),
            'stages' => IpcBatch::STAGES,
        ]);
    }

    public function show(IpcBatch $batch): Response
    {
        $batch->load(['masterProduct', 'masterLine', 'creator', 'startupCheck', 'fillingCheck', 'packingCheck', 'finishedCheck']);

        $stageIndex = array_search($batch->current_stage, IpcBatch::STAGES, true);

        $builtStages = [
            IpcBatch::STAGE_STARTUP => route('startup-check.edit', $batch),
            IpcBatch::STAGE_FILLING => route('filling-check.edit', $batch),
            IpcBatch::STAGE_PACKING => route('packing-check.edit', $batch),
            IpcBatch::STAGE_FINISHED => route('finished-check.edit', $batch),
            IpcBatch::STAGE_APPROVAL => route('approval.edit', $batch),
            IpcBatch::STAGE_PRINT => route('print.edit', $batch),
        ];

        $labels = [
            IpcBatch::STAGE_STARTUP => 'Startup Check',
            IpcBatch::STAGE_FILLING => 'Filling Check',
            IpcBatch::STAGE_PACKING => 'Packing Check',
            IpcBatch::STAGE_FINISHED => 'Finished Good',
            IpcBatch::STAGE_APPROVAL => 'Approval',
            IpcBatch::STAGE_PRINT => 'Print',
            IpcBatch::STAGE_COMPLETED => 'Selesai',
        ];

        $stages = collect($labels)->map(function ($label, $key) use ($stageIndex, $builtStages) {
            $thisIndex = array_search($key, IpcBatch::STAGES, true);

            $status = match (true) {
                $thisIndex < $stageIndex => 'done',
                // 'completed' has no page of its own — reaching it (batch.current_stage ===
                // 'completed') means every prior stage, print included, is genuinely finished.
                $thisIndex === $stageIndex && $key === IpcBatch::STAGE_COMPLETED => 'done',
                $thisIndex === $stageIndex => 'active',
                default => 'locked',
            };

            return [
                'key' => $key,
                'label' => $label,
                'status' => $status,
                'href' => $status !== 'locked' ? ($builtStages[$key] ?? null) : null,
                'available' => array_key_exists($key, $builtStages),
            ];
        })->values();

        return Inertia::render('batches/show', [
            'batch' => $batch,
            'stages' => $stages,
        ]);
    }

    public function create(): Response
    {
        // No product list here on purpose — the FG Code picker searches `lookup.products`
        // on demand, since shipping the whole master (8000+ rows) froze tablets.
        return Inertia::render('batches/create');
    }

    public function store(StoreIpcBatchRequest $request): RedirectResponse
    {
        $bulkCode = MasterProductBulkCode::findOrFail($request->validated('master_product_bulk_code_id'));

        $batch = IpcBatch::create([
            'master_product_id' => $request->validated('master_product_id'),
            'master_product_bulk_code_id' => $bulkCode->id,
            'no_batch' => $request->validated('no_batch'),
            'bulk_code' => $bulkCode->bulk_code,
            'created_by' => $request->user()->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
        ]);

        return redirect()->route('startup-check.edit', $batch)->with('success', 'Batch dibuat. Lanjutkan ke Startup Check.');
    }
}
