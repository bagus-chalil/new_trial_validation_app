<?php

namespace App\Http\Controllers;

use App\Models\Trial;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * All Trials page (2026-10-19, owner request): one read-only page that
 * every logged-in user can open, listing every trial still moving through the
 * process regardless of who owns it, so a manager can see where trials are
 * piling up. Deliberately does NOT use Trial::scopeVisibleTo() — that scope
 * narrows by role/department, which is exactly what this page must not do.
 *
 * Only header-level facts are exposed (code, product, status, who holds it,
 * idle days). The detail link is shown only when TrialPolicy::view() allows
 * it, so another user's Draft is listed but never opened.
 *
 * "Idle days" is days since trials_header.updated_at, i.e. since the last
 * activity on the trial (a department review also touches the header, so the
 * clock restarts per review). It is a movement signal, not an exact SLA.
 */
class TrialMonitorController extends Controller
{
    /** Statuses that are still in motion; Approved/Rejected are finished. */
    private const ACTIVE_STATUSES = ['Draft', 'In Review', 'Ready for Approval', 'Need Revision'];

    /** Idle-day thresholds for the amber / red highlight (reviews often take 1-2 weeks). */
    private const WARN_DAYS = 14;

    private const CRITICAL_DAYS = 30;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $now = Carbon::now();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'holder' => trim((string) $request->query('holder', '')),
        ];

        $active = Trial::query()
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->whereIn('progress_status', self::ACTIVE_STATUSES)
            ->with('creator:id,name,email')
            ->get(['id', 'progress_status', 'pending_with', 'created_by', 'created_at', 'updated_at']);

        /** @var array<string, array{count: int, total: int, oldest: int, stale: int}> $stages */
        $stages = [];
        /** @var array<string, array{name: string, kind: string, count: int, total: int, oldest: int, ids: list<int>}> $holders */
        $holders = [];

        foreach ($active as $trial) {
            $days = $this->idleDays($trial, $now);
            $status = (string) $trial->progress_status;

            $stage = $stages[$status] ?? ['count' => 0, 'total' => 0, 'oldest' => 0, 'stale' => 0];
            $stage['count']++;
            $stage['total'] += $days;
            $stage['oldest'] = max($stage['oldest'], $days);
            $stage['stale'] += $days >= self::WARN_DAYS ? 1 : 0;
            $stages[$status] = $stage;

            foreach ($this->holdersOf($trial) as $holder) {
                $key = Str::lower($holder['name']);
                $entry = $holders[$key] ?? [
                    'name' => $holder['name'],
                    'kind' => $holder['kind'],
                    'count' => 0,
                    'total' => 0,
                    'oldest' => 0,
                    'ids' => [],
                ];
                $entry['count']++;
                $entry['total'] += $days;
                $entry['oldest'] = max($entry['oldest'], $days);
                $entry['ids'][] = $trial->id;
                $holders[$key] = $entry;
            }
        }

        $holderIds = null;
        if ($filters['holder'] !== '') {
            $holderIds = $holders[Str::lower($filters['holder'])]['ids'] ?? [];
        }

        $trials = Trial::query()
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->whereIn('progress_status', self::ACTIVE_STATUSES)
            ->search(['q' => $filters['q'], 'status' => $filters['status']])
            ->when($holderIds !== null, fn ($query) => $query->whereIn('id', $holderIds))
            ->with('creator:id,name,email')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $trials->getCollection()->each(function (Trial $trial) use ($user, $now) {
            $trial->setAttribute('idle_days', $this->idleDays($trial, $now));
            $trial->setAttribute('holders', array_column($this->holdersOf($trial), 'name'));
            $trial->setAttribute('owner_name', $trial->creator?->name ?: $trial->created_by);
            $trial->setAttribute('can_view', Gate::forUser($user)->allows('view', $trial));
        });

        return Inertia::render('monitoring/index', [
            'trials' => $trials,
            'filters' => $filters,
            'stages' => collect(self::ACTIVE_STATUSES)->map(function (string $status) use ($stages) {
                $stage = $stages[$status] ?? ['count' => 0, 'total' => 0, 'oldest' => 0, 'stale' => 0];

                return [
                    'status' => $status,
                    'count' => $stage['count'],
                    'avg_days' => $stage['count'] > 0 ? round($stage['total'] / $stage['count'], 1) : 0,
                    'oldest_days' => $stage['oldest'],
                    'stale' => $stage['stale'],
                ];
            })->values(),
            'holders' => collect($holders)
                ->map(fn (array $holder) => [
                    'name' => $holder['name'],
                    'kind' => $holder['kind'],
                    'count' => $holder['count'],
                    'avg_days' => round($holder['total'] / $holder['count'], 1),
                    'oldest_days' => $holder['oldest'],
                ])
                ->sortByDesc(fn (array $holder) => [$holder['count'], $holder['oldest_days']])
                ->take(10)
                ->values(),
            'thresholds' => ['warn' => self::WARN_DAYS, 'critical' => self::CRITICAL_DAYS],
        ]);
    }

    private function idleDays(Trial $trial, Carbon $now): int
    {
        $since = $trial->updated_at ?? $trial->created_at;

        if ($since === null) {
            return 0;
        }

        return max(0, (int) floor($since->diffInDays($now)));
    }

    /**
     * Who the trial is currently waiting on. Draft / Need Revision wait on
     * the trial's owner; In Review waits on each department still pending
     * (pending_with is a comma-separated list of department codes); Ready for
     * Approval waits on the assigned approver.
     *
     * @return list<array{name: string, kind: string}>
     */
    private function holdersOf(Trial $trial): array
    {
        $owner = $trial->creator?->name ?: ($trial->created_by ?: '-');

        switch ($trial->progress_status) {
            case 'In Review':
                $departments = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) $trial->pending_with),
                ), fn (string $value) => $value !== ''));

                return array_map(
                    fn (string $department) => ['name' => $department, 'kind' => 'team'],
                    $departments !== [] ? $departments : ['-'],
                );
            case 'Ready for Approval':
                return [['name' => trim((string) $trial->pending_with) ?: '-', 'kind' => 'approver']];
            default:
                return [['name' => $owner, 'kind' => 'owner']];
        }
    }
}
