<?php

namespace App\Services\Projects;

use App\Models\Improvement;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectStage;
use App\Models\PurchaseOrder;
use App\Models\Tooling;
use App\Models\User;
use App\Policies\ImprovementPolicy;
use App\Support\ToolingOptions;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data for the Project dashboard, a port of the old Project Tracking
 * dashboard (rDash): date-range filter, status / category donuts, the four
 * feature cards (improvement, tooling, quotation, PO), the priority
 * reminders, the "needs attention" list and the progress board.
 *
 * Status rules match the old app's effStatus(): Completed wins, then On Hold,
 * then an unfinished project past its end date is Delayed.
 */
class ProjectDashboard
{
    public const PERIODS = ['all', 'month', 'quarter', 'year', 'custom'];

    public const REMINDER_PERIODS = ['day', 'week', 'month'];

    public const STATUS_ORDER = ['On Progress', 'Delayed', 'On Hold', 'Not Started', 'Completed'];

    private Carbon $today;

    public function __construct(?Carbon $today = null)
    {
        $this->today = ($today ?? Carbon::today())->copy()->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, string $period, ?string $start, ?string $end, string $reminder, bool $canFinance): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'all';
        $reminder = in_array($reminder, self::REMINDER_PERIODS, true) ? $reminder : 'week';
        $range = $this->range($period, $start, $end);

        $all = Project::query()->whereNull('deleted_at')
            ->with(['stages', 'milestones', 'quotations'])
            ->get();

        $inRange = $this->filterByRange($all, $range);
        $open = $inRange->filter(fn (Project $p) => $this->effStatus($p) !== 'Completed');

        $unlisted = $range['all'] ? 0 : $all->filter(fn (Project $p) => ! $this->hasValidDates($p))->count();

        return [
            'range' => [
                'period' => $period,
                'start' => $range['start']?->toDateString(),
                'end' => $range['end']?->toDateString(),
                'valid' => $range['valid'],
                'unlisted' => $unlisted,
            ],
            'reminderPeriod' => $reminder,
            'kpi' => $this->kpi($inRange),
            'statusDonut' => collect(self::STATUS_ORDER)->map(fn (string $s) => [
                'label' => $s,
                'value' => $inRange->filter(fn (Project $p) => $this->effStatus($p) === $s)->count(),
            ])->values()->all(),
            'categoryDonut' => $inRange->groupBy(fn (Project $p) => $p->category !== '' ? $p->category : '-')
                ->map(fn (Collection $g, string $name) => ['label' => $name, 'value' => $g->count()])
                ->sortByDesc('value')->values()->all(),
            'cards' => $this->cards($user, $inRange, $canFinance),
            'lists' => $this->lists($user, $inRange, $canFinance),
            'reminders' => $this->reminders($open, $reminder, $user),
            'attention' => $this->attention($open),
            'board' => $this->board($inRange),
        ];
    }

    /**
     * Compact all-time overview (projects, improvement, check tooling and the
     * top items needing attention) for the main Dashboard. No finance data.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        $projects = Project::query()->whereNull('deleted_at')->with(['stages', 'milestones', 'quotations'])->get();

        return [
            'kpi' => $this->kpi($projects),
            'cards' => array_intersect_key($this->cards($user, $projects, false), array_flip(['improvement', 'tooling'])),
            'attention' => array_slice($this->attention($projects), 0, 4),
        ];
    }

    public function effStatus(Project $p): string
    {
        if ($p->status === 'Completed' || (int) $p->progress >= 100) {
            return 'Completed';
        }

        if ($p->status === 'On Hold') {
            return 'On Hold';
        }

        if ($p->end_date !== null && $p->end_date->copy()->startOfDay()->lt($this->today)) {
            return 'Delayed';
        }

        return $p->status;
    }

    public function stageStatus(ProjectStage $s): string
    {
        if ($s->actual_end !== null || $s->status === 'Completed' || (int) $s->progress >= 100) {
            return 'Completed';
        }

        if ($s->status === 'On Hold') {
            return 'On Hold';
        }

        if ($s->plan_end !== null && $s->plan_end->copy()->startOfDay()->lt($this->today)) {
            return 'Delayed';
        }

        if ($s->actual_start !== null || $s->status === 'On Progress' || (int) $s->progress > 0) {
            return 'On Progress';
        }

        return 'Not Started';
    }

    /**
     * @return array{all: bool, valid: bool, start: ?Carbon, end: ?Carbon}
     */
    private function range(string $period, ?string $start, ?string $end): array
    {
        $now = $this->today;

        return match ($period) {
            'month' => ['all' => false, 'valid' => true, 'start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()->startOfDay()],
            'quarter' => ['all' => false, 'valid' => true, 'start' => $now->copy()->firstOfQuarter(), 'end' => $now->copy()->lastOfQuarter()->startOfDay()],
            'year' => ['all' => false, 'valid' => true, 'start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()->startOfDay()],
            'custom' => $this->customRange($start, $end),
            default => ['all' => true, 'valid' => true, 'start' => null, 'end' => null],
        };
    }

    /**
     * @return array{all: bool, valid: bool, start: ?Carbon, end: ?Carbon}
     */
    private function customRange(?string $start, ?string $end): array
    {
        $s = $this->date($start);
        $e = $this->date($end);

        return [
            'all' => false,
            'valid' => $s !== null && $e !== null && $s->lte($e),
            'start' => $s,
            'end' => $e,
        ];
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date->startOfDay() : null;
    }

    private function hasValidDates(Project $p): bool
    {
        return $p->start_date !== null && $p->end_date !== null && $p->start_date->lte($p->end_date);
    }

    /**
     * Same rule as the old dashboardProjects(): projects whose schedule
     * overlaps the range; a finished project counts by its completion date.
     *
     * @param  Collection<int, Project>  $projects
     * @param  array{all: bool, valid: bool, start: ?Carbon, end: ?Carbon}  $range
     * @return Collection<int, Project>
     */
    private function filterByRange(Collection $projects, array $range): Collection
    {
        if ($range['all']) {
            return $projects->values();
        }

        if (! $range['valid']) {
            return collect();
        }

        $from = $range['start'];
        $to = $range['end'];

        return $projects->filter(function (Project $p) use ($from, $to) {
            if (! $this->hasValidDates($p)) {
                return false;
            }

            $start = $p->start_date->copy()->startOfDay();
            $end = $p->end_date->copy()->startOfDay();
            $status = $this->effStatus($p);

            if ($status === 'Completed') {
                $done = $this->completionDate($p) ?? $end;

                return $done->gte($from) && $start->lte($to);
            }

            $ongoing = in_array($status, ['On Progress', 'Delayed'], true);

            return $start->lte($to) && ($end->gte($from) || ($ongoing && $this->today->gte($from)));
        })->values();
    }

    private function completionDate(Project $p): ?CarbonInterface
    {
        $lastStage = $p->stages->pluck('actual_end')->filter()->sortDesc()->first();
        if ($lastStage !== null) {
            return $lastStage->copy()->startOfDay();
        }

        if ($this->effStatus($p) === 'Completed' && $p->updated_at !== null) {
            return $p->updated_at->copy()->startOfDay();
        }

        return $p->end_date?->copy()->startOfDay();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<string, int>
     */
    private function kpi(Collection $projects): array
    {
        $count = fn (string $s) => $projects->filter(fn (Project $p) => $this->effStatus($p) === $s)->count();

        return [
            'total' => $projects->count(),
            'on_progress' => $count('On Progress'),
            'completed' => $count('Completed'),
            'delayed' => $count('Delayed'),
            'on_hold' => $count('On Hold'),
            'not_started' => $count('Not Started'),
            'avg_progress' => $projects->isEmpty() ? 0 : (int) round($projects->avg('progress')),
        ];
    }

    /**
     * The four feature cards (Improvement, Check Tooling, Quotation, PO).
     * Quotation and PO are finance data and only sent to those who may see it.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<string, mixed>
     */
    private function cards(User $user, Collection $projects, bool $canFinance): array
    {
        $policy = app(ImprovementPolicy::class);
        $improvements = Improvement::query()->whereNull('deleted_at')
            ->when(! $policy->seesAll($user), fn ($q) => $q->where('submitted_by_user_id', $user->id))
            ->get(['id', 'status', 'project_id']);
        $projectProgress = Project::query()->whereIn('id', $improvements->pluck('project_id')->filter())
            ->whereNull('deleted_at')->pluck('progress', 'id');

        $impPct = $improvements->map(fn (Improvement $i) => $this->improvementProgress($i, $projectProgress->get($i->project_id)));

        $toolings = Tooling::query()->visibleTo($user)->whereNull('deleted_at')->with(['approvals', 'items'])->get();
        $activeToolings = $toolings->filter(fn (Tooling $t) => in_array($t->status, [
            ToolingOptions::STATUS_WAITING_RNI_SAMPLE, ToolingOptions::STATUS_WAITING_RNI, ToolingOptions::STATUS_WAITING_SAMPLE,
            ToolingOptions::STATUS_PIE_CHECK, ToolingOptions::STATUS_PRODUCTION, ToolingOptions::STATUS_HOLD,
        ], true))->count();
        $tlgPct = $toolings->map(fn (Tooling $t) => $this->toolingProgress($t));

        $cards = [
            'improvement' => [
                'count' => $improvements->count(),
                'new' => $improvements->where('status', 'Baru')->count(),
                'avg_progress' => $impPct->isEmpty() ? 0 : (int) round($impPct->avg()),
            ],
            'tooling' => [
                'count' => $toolings->count(),
                'active' => $activeToolings,
                'avg_progress' => $tlgPct->isEmpty() ? 0 : (int) round($tlgPct->avg()),
            ],
            'quotation' => null,
            'po' => null,
        ];

        if ($canFinance) {
            $waitingQuotation = $projects->filter(fn (Project $p) => ! in_array($this->effStatus($p), ['Completed', 'On Hold', 'Not Started'], true)
                && $p->quotations->isEmpty());
            $poCount = PurchaseOrder::query()->whereIn('project_id', $projects->pluck('id'))->count();
            $projectsWithPo = PurchaseOrder::query()->whereIn('project_id', $projects->pluck('id'))->pluck('project_id')->unique();
            $waitingPo = $projects->filter(fn (Project $p) => $this->effStatus($p) !== 'Completed'
                && $p->quotations->contains('status', 'Terpilih')
                && ! $projectsWithPo->contains($p->id));

            $cards['quotation'] = [
                'count' => $projects->sum(fn (Project $p) => $p->quotations->count()),
                'waiting' => $waitingQuotation->count(),
            ];
            $cards['po'] = ['count' => $poCount, 'waiting' => $waitingPo->count()];
        }

        return $cards;
    }

    /**
     * Row lists behind the four feature cards (the old "summary progress"
     * pop-ups): who/what/status/progress per improvement, check tooling,
     * quotation and PO. Quotation and PO are finance data.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function lists(User $user, Collection $projects, bool $canFinance): array
    {
        $policy = app(ImprovementPolicy::class);
        $improvements = Improvement::query()->whereNull('deleted_at')
            ->when(! $policy->seesAll($user), fn ($q) => $q->where('submitted_by_user_id', $user->id))
            ->orderByDesc('id')->limit(300)->get();
        $projectsById = Project::query()->whereIn('id', $improvements->pluck('project_id')->filter())
            ->whereNull('deleted_at')->get(['id', 'code', 'progress'])->keyBy('id');

        $projectOf = fn (?int $id): ?Project => $id === null ? null : $projectsById->get($id);

        $toolings = Tooling::query()->visibleTo($user)->whereNull('deleted_at')
            ->with(['approvals', 'items'])->orderByDesc('updated_at')->orderByDesc('id')->limit(300)->get();

        $lists = [
            'improvement' => $improvements->map(fn (Improvement $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'subtitle' => trim($i->department.' · '.$i->submitted_by_name, ' ·'),
                'status' => $i->status,
                'progress' => $this->improvementProgress($i, $projectOf($i->project_id)?->progress),
                'extra' => $projectOf($i->project_id)->code ?? '',
                'date' => $i->submitted_at?->toDateString(),
            ])->values()->all(),
            'tooling' => $toolings->map(fn (Tooling $t) => [
                'id' => $t->id,
                'title' => $t->product_name !== '' ? $t->product_name : '-',
                'subtitle' => trim($t->npd_no.' · '.$t->client_code, ' ·'),
                'status' => $t->status,
                'progress' => $this->toolingProgress($t),
                'extra' => $t->need_invest === 'YES' ? 'Invest' : '',
                'critical' => (bool) $t->is_critical,
                'date' => $t->request_date?->toDateString(),
            ])->values()->all(),
            'quotation' => [],
            'po' => [],
        ];

        if ($canFinance) {
            $ids = $projects->pluck('id');
            $byProject = $projects->keyBy('id');

            $lists['quotation'] = ProjectQuotation::query()->whereIn('project_id', $ids)
                ->orderByDesc('quotation_date')->orderByDesc('id')->limit(300)->get()
                ->map(fn ($q) => [
                    'id' => $q->id,
                    'title' => $q->vendor_name,
                    'subtitle' => trim($q->quotation_no.' · '.($byProject->get($q->project_id)->name ?? ''), ' ·'),
                    'status' => $q->status,
                    'amount' => (float) $q->amount,
                    'currency' => $q->currency,
                    'project_id' => $q->project_id,
                    'date' => $q->quotation_date?->toDateString(),
                ])->values()->all();

            $lists['po'] = PurchaseOrder::query()->whereIn('project_id', $ids)
                ->orderByDesc('po_date')->orderByDesc('id')->limit(300)->get()
                ->map(fn (PurchaseOrder $po) => [
                    'id' => $po->id,
                    'title' => $po->po_no,
                    'subtitle' => trim($po->vendor_name.' · '.($byProject->get($po->project_id)->name ?? ''), ' ·'),
                    'project_id' => $po->project_id,
                    'date' => $po->po_date?->toDateString(),
                ])->values()->all();
        }

        return $lists;
    }

    private function improvementProgress(Improvement $i, mixed $projectProgress): int
    {
        if ($i->status === 'Selesai') {
            return 100;
        }

        if ($i->project_id !== null && $projectProgress !== null) {
            return min(99, 75 + (int) round(((int) $projectProgress) * 0.25));
        }

        if (in_array($i->status, ['Disetujui', 'Tidak Perlu Improvement', 'Ditolak'], true)) {
            return $i->status === 'Disetujui' ? 75 : 100;
        }

        if (in_array($i->status, ['Review PI', 'Menunggu Data'], true)) {
            return 50;
        }

        return 25;
    }

    private function toolingProgress(Tooling $t): int
    {
        if ($t->status === ToolingOptions::STATUS_APPROVED || ($t->isSigned('pi') && $t->isSigned('production'))) {
            return 100;
        }

        if ($t->isSigned('pi') || $t->status === ToolingOptions::STATUS_PRODUCTION) {
            return 75;
        }

        if ($t->isSigned('prni') && $t->isSigned('rni')) {
            $started = $t->recommended_machine !== '' || $t->status === ToolingOptions::STATUS_PIE_CHECK
                || $t->items->contains(fn ($i) => $i->actual !== '' || $i->status !== '');

            return $started ? 50 : 35;
        }

        return 25;
    }

    /**
     * Upcoming and overdue dates inside the window (today / the next 6 days /
     * to the end of the month): project target, stage plan end, milestones and
     * unfinished Check Tooling requests (due date or 5 working days).
     *
     * @param  Collection<int, Project>  $projects
     * @return list<array<string, mixed>>
     */
    private function reminders(Collection $projects, string $period, User $user): array
    {
        $end = match ($period) {
            'day' => $this->today->copy(),
            'week' => $this->today->copy()->addDays(6),
            default => $this->today->copy()->endOfMonth()->startOfDay(),
        };
        $out = [];

        foreach ($projects as $p) {
            if ($this->effStatus($p) === 'On Hold') {
                continue;
            }

            $add = function (?CarbonInterface $date, string $kind, string $label) use (&$out, $p, $end) {
                if ($date === null || $date->copy()->startOfDay()->gt($end)) {
                    return;
                }

                $out[] = [
                    'kind' => $kind,
                    'label' => $label,
                    'date' => $date->toDateString(),
                    'days' => (int) $this->today->diffInDays($date->copy()->startOfDay(), false),
                    'project' => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'pic_name' => $p->pic_name],
                    'tooling' => null,
                ];
            };

            if ($this->effStatus($p) !== 'Completed') {
                $add($p->end_date, 'project', '');
            }

            foreach ($p->stages as $s) {
                $status = $this->stageStatus($s);
                if ($status !== 'On Hold' && $status !== 'Completed') {
                    $add($s->plan_end, 'stage', $s->name);
                }
            }

            foreach ($p->milestones as $m) {
                if ($m->status !== 'Done') {
                    $add($m->due_date, 'milestone', $m->name);
                }
            }
        }

        $toolingEnd = $period === 'day' ? $this->today->copy()->addDays(7) : $end;
        $toolings = Tooling::query()->visibleTo($user)->whereNull('deleted_at')
            ->whereNotIn('status', ToolingOptions::FINAL_STATUSES)->get();

        foreach ($toolings as $t) {
            $due = $t->due_date ?? ($t->request_date !== null ? $this->addWorkingDays($t->request_date->copy(), 5) : null);
            if ($due === null || $due->copy()->startOfDay()->gt($toolingEnd)) {
                continue;
            }

            $out[] = [
                'kind' => 'tooling',
                'label' => $t->product_name,
                'date' => $due->toDateString(),
                'days' => (int) $this->today->diffInDays($due->copy()->startOfDay(), false),
                'project' => null,
                'tooling' => [
                    'id' => $t->id,
                    'npd_no' => $t->npd_no,
                    'status' => $t->status,
                    'is_critical' => (bool) $t->is_critical,
                ],
            ];
        }

        usort($out, fn (array $a, array $b) => [$a['date'], $a['label']] <=> [$b['date'], $b['label']]);

        return array_slice($out, 0, 60);
    }

    private function addWorkingDays(CarbonInterface $date, int $days): CarbonInterface
    {
        while ($days > 0) {
            $date = $date->addDay();
            if (! $date->isWeekend()) {
                $days--;
            }
        }

        return $date;
    }

    /**
     * "Perlu Perhatian": delayed, at risk (progress well behind the plan),
     * on hold, deadline in 14 days, late stages and stages not started on time.
     *
     * @param  Collection<int, Project>  $projects
     * @return list<array<string, mixed>>
     */
    private function attention(Collection $projects): array
    {
        $out = [];

        foreach ($projects as $p) {
            $status = $this->effStatus($p);
            if ($status === 'Completed') {
                continue;
            }

            $days = $p->end_date !== null ? (int) $this->today->diffInDays($p->end_date->copy()->startOfDay(), false) : null;
            $planned = $this->planned($p);
            $ref = ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'pic_name' => $p->pic_name];

            if ($status === 'Delayed') {
                $out[] = ['project' => $ref, 'sev' => 1, 'tag' => 'late', 'days' => $days === null ? null : max(0, -$days), 'progress' => (int) $p->progress, 'planned' => $planned, 'items' => []];
            } elseif ($status === 'On Progress' && $planned - (int) $p->progress >= 15) {
                $out[] = ['project' => $ref, 'sev' => 2, 'tag' => 'at_risk', 'days' => null, 'progress' => (int) $p->progress, 'planned' => $planned, 'items' => []];
            } elseif ($status === 'On Hold') {
                $out[] = ['project' => $ref, 'sev' => 4, 'tag' => 'on_hold', 'days' => null, 'progress' => (int) $p->progress, 'planned' => $planned, 'items' => []];
            } elseif ($days !== null && $days >= 0 && $days <= 14) {
                $out[] = ['project' => $ref, 'sev' => 5, 'tag' => 'deadline', 'days' => $days, 'progress' => (int) $p->progress, 'planned' => $planned, 'items' => []];
            }

            $lateStages = $p->stages->filter(fn (ProjectStage $s) => $this->stageStatus($s) === 'Delayed');
            if ($lateStages->isNotEmpty()) {
                $out[] = ['project' => $ref, 'sev' => 1.5, 'tag' => 'late_stage', 'days' => null, 'progress' => (int) $p->progress, 'planned' => $planned,
                    'items' => $lateStages->take(3)->map(fn (ProjectStage $s) => $s->name.' (+'.max(0, (int) $s->plan_end->copy()->startOfDay()->diffInDays($this->today, false)).')')->values()->all()];
            }

            $lateStart = $p->stages->filter(fn (ProjectStage $s) => $this->stageStatus($s) === 'Not Started'
                && $s->plan_start !== null && $s->plan_start->copy()->startOfDay()->lt($this->today));
            if ($lateStart->isNotEmpty()) {
                $out[] = ['project' => $ref, 'sev' => 3, 'tag' => 'late_start', 'days' => null, 'progress' => (int) $p->progress, 'planned' => $planned,
                    'items' => $lateStart->take(3)->map(fn (ProjectStage $s) => $s->name)->values()->all()];
            }
        }

        usort($out, fn (array $a, array $b) => $a['sev'] <=> $b['sev']);

        return array_slice($out, 0, 40);
    }

    private function planned(Project $p): int
    {
        if ($p->start_date === null || $p->end_date === null) {
            return 0;
        }

        $start = $p->start_date->copy()->startOfDay();
        $end = $p->end_date->copy()->startOfDay();

        if ($this->today->lte($start)) {
            return 0;
        }

        if ($this->today->gte($end)) {
            return 100;
        }

        return (int) round($start->diffInDays($this->today) / max(1, $start->diffInDays($end)) * 100);
    }

    /**
     * Progress board: every project in range, by status then deadline, with
     * the stage strip, the current stage and the number of late stages.
     *
     * @param  Collection<int, Project>  $projects
     * @return list<array<string, mixed>>
     */
    private function board(Collection $projects): array
    {
        return array_values($projects
            ->sortBy([
                fn (Project $a, Project $b) => array_search($this->effStatus($a), self::STATUS_ORDER, true) <=> array_search($this->effStatus($b), self::STATUS_ORDER, true),
                fn (Project $a, Project $b) => [$a->end_date === null, $a->end_date?->toDateString()] <=> [$b->end_date === null, $b->end_date?->toDateString()],
            ])
            ->map(function (Project $p) {
                $status = $this->effStatus($p);
                $stages = $p->stages->sortBy('id')->values();
                $statuses = $stages->map(fn (ProjectStage $s) => $this->stageStatus($s));
                $done = $statuses->filter(fn (string $s) => $s === 'Completed')->count();
                $currentIdx = $statuses->keys()->filter(fn ($i) => in_array($statuses[$i], ['On Progress', 'Delayed', 'On Hold'], true))->values();
                if ($currentIdx->isEmpty() && $done < $stages->count()) {
                    $first = $statuses->search('Not Started');
                    $currentIdx = $first === false ? collect() : collect([$first]);
                }

                return [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'category' => $p->category,
                    'pic_name' => $p->pic_name,
                    'status' => $status,
                    'progress' => (int) $p->progress,
                    'planned' => $status === 'Completed' ? null : $this->planned($p),
                    'at_risk' => $status === 'On Progress' && $this->planned($p) - (int) $p->progress >= 15,
                    'start_date' => $p->start_date?->toDateString(),
                    'end_date' => $p->end_date?->toDateString(),
                    'completed_on' => $status === 'Completed' ? $this->completionDate($p)?->toDateString() : null,
                    'stages_total' => $stages->count(),
                    'stages_done' => $done,
                    'late_stages' => $statuses->filter(fn (string $s) => $s === 'Delayed')->count(),
                    'current_stage' => $stages->count() > 0 && $done === $stages->count()
                        ? null
                        : $currentIdx->map(fn ($i) => $stages[$i]->name)->implode(' + '),
                    'stage_strip' => $stages->map(fn (ProjectStage $s, int $i) => ['name' => $s->name, 'status' => $statuses[$i]])->all(),
                    'stages' => $stages->map(fn (ProjectStage $s, int $i) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'status' => $statuses[$i],
                        'pic_name' => $s->pic_name,
                        'plan_start' => $s->plan_start?->toDateString(),
                        'plan_end' => $s->plan_end?->toDateString(),
                        'actual_start' => $s->actual_start?->toDateString(),
                        'actual_end' => $s->actual_end?->toDateString(),
                        'progress' => (int) $s->progress,
                    ])->all(),
                ];
            })
            ->values()
            ->all());
    }
}
