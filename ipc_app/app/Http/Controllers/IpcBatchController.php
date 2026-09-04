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
            ->with(['masterProduct', 'masterLine', 'creator', 'startupCheck', 'fillingCheck', 'packingCheck', 'finishedCheck'])
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
