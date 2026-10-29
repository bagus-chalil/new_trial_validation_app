<?php

namespace App\Http\Controllers;

use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Services\TrialStatus\TrialStatusClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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
            $updatedAt = $trial['latest_updated_at'] ?? null;

            return [
                'fg_code' => (string) $fgCode,
                'product_name' => $product?->product_name ?? ($trial['product_name'] ?? ''),
                'status' => $trial['status'],
                'trial_count' => $trial['trial_count'],
                'latest_trial_code' => $trial['latest_trial_code'],
                'latest_stage' => $trial['latest_progress_status'] ?? 'Lainnya',
                'latest_step' => $trial['latest_step'],
                'pending_with' => $trial['pending_with'],
                'last_approved_at' => $trial['last_approved_at'],
                'idle_days' => $trial['status'] === 'in_progress' && $updatedAt
                    ? (int) floor(Carbon::parse($updatedAt)->diffInDays(now()))
                    : null,
                'in_master' => $product !== null,
                'ipc_batches' => $product ? (int) ($batchCounts[$product->id] ?? 0) : 0,
            ];
        })->values();

        $q = mb_strtolower($request->string('q')->toString());
        $searched = $q === ''
            ? $rows
            : $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['fg_code'].' '.$row['product_name'].' '.$row['latest_trial_code']), $q));

        $status = $request->string('status')->toString();
        $stage = $request->string('stage')->toString();

        $filtered = $searched
            ->when(in_array($status, self::STATUSES, true), fn (Collection $c) => $c->where('status', $status))
            ->when($stage !== '', fn (Collection $c) => $c->where('status', 'in_progress')->where('latest_stage', $stage))
            ->when($request->string('master')->toString() === 'missing', fn (Collection $c) => $c->where('in_master', false))
            ->when($request->boolean('stale'), fn (Collection $c) => $c->filter(fn (array $row) => $this->isStale($row)))
            ->sortBy(fn (array $row) => $request->boolean('stale') ? -($row['idle_days'] ?? 0) : mb_strtolower($row['product_name']))
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
            'filters' => $request->only('q', 'status', 'stage', 'master', 'stale'),
            'counts' => [
                'all' => $searched->count(),
                'approved' => $searched->where('status', 'approved')->count(),
                'in_progress' => $searched->where('status', 'in_progress')->count(),
                'rejected' => $searched->where('status', 'rejected')->count(),
                'in_master' => $searched->where('in_master', true)->count(),
                'missing' => $searched->where('in_master', false)->count(),
                'stale' => $searched->filter(fn (array $row) => $this->isStale($row))->count(),
            ],
            'stages' => $searched->where('status', 'in_progress')
                ->groupBy('latest_stage')
                ->map(fn (Collection $group, string $name) => ['name' => $name, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values(),
            'staleDays' => $this->staleDays(),
            'trialAppAvailable' => $ok,
        ]);
    }

    /** An unfinished trial untouched for this many days is flagged "perlu perhatian" (TRIAL_STALE_DAYS). */
    private function staleDays(): int
    {
        return max(1, (int) config('services.trial_app.stale_days'));
    }

    /** @param  array<string, mixed>  $row */
    private function isStale(array $row): bool
    {
        return $row['status'] === 'in_progress' && ($row['idle_days'] ?? 0) >= $this->staleDays();
    }
}
