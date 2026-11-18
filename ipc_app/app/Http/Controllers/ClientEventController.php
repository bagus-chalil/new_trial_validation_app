<?php

namespace App\Http\Controllers;

use App\Models\IpcClientEvent;
use App\Services\Vision\ClientIssueAnalyzer;
use App\Support\ClientEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Field-issue log. `store` is the browser reporter's endpoint (also open to guests so a login-page
 * problem is seen too, hence throttled); the rest is the admin "Log Kendala" screen.
 */
class ClientEventController extends Controller
{
    private const MAX_BATCH = 20;

    private const PER_PAGE = 20;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:'.self::MAX_BATCH],
            'events.*' => ['array'],
            'events.*.kind' => ['required', 'string', 'max:30'],
            'events.*.message' => ['required', 'string', 'max:2000'],
            'events.*.detail' => ['nullable', 'string', 'max:10000'],
            'events.*.url' => ['nullable', 'string', 'max:2000'],
            'events.*.online' => ['nullable', 'boolean'],
            'events.*.net_type' => ['nullable', 'string', 'max:20'],
            'events.*.app_version' => ['nullable', 'string', 'max:40'],
            'events.*.occurred_at' => ['nullable', 'string', 'max:40'],
        ]);

        foreach ($data['events'] as $event) {
            ClientEventRecorder::record($event, $request);
        }

        Cache::forget(self::OPEN_COUNT_CACHE);

        return response()->json(['ok' => true]);
    }

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['open', 'resolved', 'all'], true) ? $request->query('status') : 'open';
        $kind = in_array($request->query('kind'), IpcClientEvent::KINDS, true) ? $request->query('kind') : null;
        $days = in_array((int) $request->query('days'), [1, 7, 30, 90], true) ? (int) $request->query('days') : 7;
        $since = now()->subDays($days);

        $base = fn () => IpcClientEvent::query()
            ->where('occurred_at', '>=', $since)
            ->when($kind, fn ($q) => $q->where('kind', $kind));

        $issues = $base()
            ->selectRaw('fingerprint, COUNT(*) AS events, SUM(resolved_at IS NULL) AS open_events, COUNT(DISTINCT user_id) AS users, MAX(id) AS last_id, MIN(occurred_at) AS first_seen, MAX(occurred_at) AS last_seen')
            ->groupBy('fingerprint')
            ->when($status === 'open', fn ($q) => $q->havingRaw('SUM(resolved_at IS NULL) > 0'))
            ->when($status === 'resolved', fn ($q) => $q->havingRaw('SUM(resolved_at IS NULL) = 0'))
            ->orderByDesc('last_seen')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $fingerprints = collect($issues->items())->pluck('fingerprint')->all();
        $latest = IpcClientEvent::with('user:id,name')
            ->whereIn('id', collect($issues->items())->pluck('last_id'))
            ->get()
            ->keyBy('fingerprint');

        // Which devices/browsers hit each issue: the fastest way to see "only that phone".
        $spread = $base()
            ->whereIn('fingerprint', $fingerprints)
            ->selectRaw('fingerprint, device, browser, COUNT(*) AS c')
            ->groupBy('fingerprint', 'device', 'browser')
            ->orderByDesc('c')
            ->get()
            ->groupBy('fingerprint');

        $issues->through(function ($row) use ($latest, $spread) {
            $last = $latest->get($row->fingerprint);

            return [
                'fingerprint' => $row->fingerprint,
                'kind' => $last?->kind,
                'message' => $last?->message,
                'detail' => $last?->detail,
                'url' => $last?->url,
                'batch_id' => $last?->ipc_batch_id,
                'events' => (int) $row->events,
                'open_events' => (int) $row->open_events,
                'users' => (int) $row->users,
                'first_seen' => $row->first_seen,
                'last_seen' => $row->last_seen,
                'last_user' => $last?->user?->name,
                'last_online' => $last?->online,
                'last_net_type' => $last?->net_type,
                'last_app_version' => $last?->app_version,
                'spread' => collect($spread->get($row->fingerprint, []))->take(4)->map(fn ($s) => [
                    'device' => $s->device, 'browser' => $s->browser, 'count' => (int) $s->c,
                ])->values()->all(),
            ];
        });

        return Inertia::render('client-events/index', [
            'issues' => $issues,
            'filters' => ['status' => $status, 'kind' => $kind, 'days' => $days],
            'kinds' => IpcClientEvent::KINDS,
            'aiEnabled' => (bool) config('vision.issue_ai.enabled'),
            'summary' => [
                'open_issues' => self::openIssueCount(),
                'events_today' => IpcClientEvent::where('occurred_at', '>=', now()->startOfDay())->count(),
                'top_devices' => IpcClientEvent::query()
                    ->whereNull('resolved_at')
                    ->where('occurred_at', '>=', $since)
                    ->selectRaw('device, browser, COUNT(*) AS c')
                    ->groupBy('device', 'browser')
                    ->orderByDesc('c')
                    ->limit(5)
                    ->get()
                    ->map(fn ($d) => ['device' => $d->device, 'browser' => $d->browser, 'count' => (int) $d->c])
                    ->all(),
            ],
        ]);
    }

    /** "Analisis AI": advisory summary of the open issues via the Vision Service's Ollama route. */
    public function analyze(Request $request, ClientIssueAnalyzer $analyzer): JsonResponse
    {
        abort_unless(config('vision.issue_ai.enabled'), 404);

        $days = in_array((int) $request->input('days'), [1, 7, 30, 90], true) ? (int) $request->input('days') : 7;

        // The Ollama server is serial, so a long analysis must not be stacked by double clicks.
        $lock = Cache::lock('ipc-issue-ai', 200);
        if (! $lock->get()) {
            return response()->json(['ok' => false, 'error' => 'Analisis sebelumnya masih berjalan, tunggu sebentar.'], 429);
        }

        try {
            return response()->json($analyzer->analyze($days));
        } finally {
            $lock->release();
        }
    }

    public function resolve(Request $request): RedirectResponse
    {
        $fingerprint = $request->validate(['fingerprint' => ['required', 'string', 'size:40']])['fingerprint'];

        IpcClientEvent::where('fingerprint', $fingerprint)->whereNull('resolved_at')->update(['resolved_at' => now()]);
        Cache::forget(self::OPEN_COUNT_CACHE);

        return back()->with('success', 'Kendala ditandai selesai.');
    }

    private const OPEN_COUNT_CACHE = 'ipc-client-events-open-count';

    /** Distinct unresolved issues in the last 7 days; shown as the badge on the admin menu. */
    public static function openIssueCount(): int
    {
        // This runs on every page for an admin (shared Inertia prop), so a missing table (code deployed
        // before `php artisan migrate`) must degrade to "0", never take the whole app down with a 500.
        try {
            return (int) Cache::remember(self::OPEN_COUNT_CACHE, 60, fn () => IpcClientEvent::query()
                ->whereNull('resolved_at')
                ->where('occurred_at', '>=', now()->subDays(7))
                ->distinct()
                ->count('fingerprint'));
        } catch (\Throwable) {
            return 0;
        }
    }
}
