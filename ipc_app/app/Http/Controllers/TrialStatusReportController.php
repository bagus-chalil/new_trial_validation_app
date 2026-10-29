<?php

namespace App\Http\Controllers;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Services\TrialStatus\TrialStatusClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Status Trial per Produk": every FG code that has a trial in new_trial_validation_app (the small
 * set), matched against Master Produk here (the big set) — pivoted on the trial side because most
 * master products were never trialled.
 */
class TrialStatusReportController extends Controller
{
    private const STATUSES = ['approved', 'in_progress', 'rejected'];

    public function index(Request $request, TrialStatusClient $client): Response
    {
        if ($request->boolean('refresh')) {
            $client->forget();
        }

        ['ok' => $ok, 'data' => $trials] = $client->all();

        $fgCodes = array_map('strval', array_keys($trials));
        $products = MasterProduct::query()->whereIn('fg_code', $fgCodes)->get()->keyBy('fg_code');
        $batchCounts = IpcBatch::query()
            ->whereIn('master_product_id', $products->pluck('id'))
            ->selectRaw('master_product_id, count(*) as total')
            ->groupBy('master_product_id')
            ->pluck('total', 'master_product_id');

        $rows = collect($trials)->map(function (array $trial, $fgCode) use ($products, $batchCounts) {
            $product = $products->get((string) $fgCode);

            return [
                'fg_code' => (string) $fgCode,
                'product_name' => $product?->product_name ?? ($trial['product_name'] ?? ''),
                'status' => $trial['status'],
                'trial_count' => $trial['trial_count'],
                'latest_trial_code' => $trial['latest_trial_code'],
                'latest_step' => $trial['latest_step'],
                'pending_with' => $trial['pending_with'],
                'last_approved_at' => $trial['last_approved_at'],
                'in_master' => $product !== null,
                'ipc_batches' => $product ? (int) ($batchCounts[$product->id] ?? 0) : 0,
            ];
        })->values();

        $q = mb_strtolower($request->string('q')->toString());
        $searched = $q === ''
            ? $rows
            : $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['fg_code'].' '.$row['product_name'].' '.$row['latest_trial_code']), $q));

        $status = $request->string('status')->toString();
        $master = $request->string('master')->toString();

        $filtered = $searched
            ->when(in_array($status, self::STATUSES, true), fn (Collection $c) => $c->where('status', $status))
            ->when($master === 'missing', fn (Collection $c) => $c->where('in_master', false))
            ->sortBy('product_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator(
            $filtered->forPage($page, 20)->values(),
            $filtered->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('masters/trial-status/index', [
            'rows' => $paginated,
            'filters' => $request->only('q', 'status', 'master'),
            'counts' => [
                'all' => $searched->count(),
                'approved' => $searched->where('status', 'approved')->count(),
                'in_progress' => $searched->where('status', 'in_progress')->count(),
                'rejected' => $searched->where('status', 'rejected')->count(),
                'missing' => $searched->where('in_master', false)->count(),
            ],
            'trialAppAvailable' => $ok,
        ]);
    }
}
