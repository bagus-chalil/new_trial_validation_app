<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectStage;
use App\Support\ProjectOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Gantt view of stages (plan vs actual, parallel flag) and milestones, read-only. */
class ProjectGanttController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = [
            'project' => (string) $request->query('project', ''),
            'pic' => (string) $request->query('pic', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $base = Project::query()->whereNull('deleted_at');

        // The board is for work still in flight: finished projects only appear
        // on request, or when the status filter explicitly asks for them.
        $showCompleted = $request->boolean('show_completed') || $filters['status'] === 'Completed';
        $grouped = $request->boolean('group');
        $completedCount = (clone $base)->where('status', 'Completed')->count();

        $paginator = (clone $base)
            ->when($filters['project'] !== '', fn ($q) => $q->whereKey((int) $filters['project']))
            ->when($filters['pic'] !== '', fn ($q) => $q->where('pic_name', $filters['pic']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(! $showCompleted, fn ($q) => $q->where('status', '!=', 'Completed'))
            ->with(['stages' => fn ($q) => $q->orderBy('id'), 'milestones'])
            ->when($grouped, fn ($q) => $q->orderByRaw("CASE WHEN category IS NULL OR category = '' THEN 1 ELSE 0 END")->orderBy('category'))
            ->orderByRaw("CASE status WHEN 'Completed' THEN 1 ELSE 0 END")
            ->orderBy('start_date')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $projects = $paginator->getCollection();

        return Inertia::render('projects/gantt', [
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'status' => $p->status,
                'category' => $p->category,
                'pic_name' => $p->pic_name,
                'progress' => $p->progress,
                'start_date' => $p->start_date?->format('Y-m-d'),
                'end_date' => $p->end_date?->format('Y-m-d'),
                'stages' => $p->stages->map(fn (ProjectStage $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'status' => $s->status,
                    'progress' => $s->progress,
                    'is_parallel' => $s->is_parallel,
                    'plan_start' => $s->plan_start?->format('Y-m-d'),
                    'plan_end' => $s->plan_end?->format('Y-m-d'),
                    'actual_start' => $s->actual_start?->format('Y-m-d'),
                    'actual_end' => $s->actual_end?->format('Y-m-d'),
                ])->values()->all(),
                'milestones' => $p->milestones->map(fn (ProjectMilestone $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'due_date' => $m->due_date?->format('Y-m-d'),
                    'status' => $m->status,
                ])->values()->all(),
            ])->values(),
            'filters' => $filters,
            'options' => [
                'projects' => (clone $base)->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name])->values(),
                'pics' => (clone $base)->where('pic_name', '!=', '')->distinct()->orderBy('pic_name')->pluck('pic_name'),
                'statuses' => ProjectOptions::PROJECT_STATUSES,
            ],
            'grouped' => $grouped,
            'categories' => (clone $base)->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category')->values(),
            'showCompleted' => $showCompleted,
            'completedCount' => $completedCount,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
