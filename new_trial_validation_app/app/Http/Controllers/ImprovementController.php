<?php

namespace App\Http\Controllers;

use App\Actions\Improvements\CloseImprovement;
use App\Actions\Improvements\ConvertImprovementToProject;
use App\Actions\Improvements\DeleteImprovement;
use App\Actions\Improvements\ReviewImprovement;
use App\Actions\Improvements\SaveImprovement;
use App\Actions\Validation\AssignImprovementNumber;
use App\Http\Requests\Improvements\ReviewImprovementRequest;
use App\Http\Requests\Improvements\SaveImprovementRequest;
use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Policies\ImprovementPolicy;
use App\Support\ProjectOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Improvement board of the Projects module (PROJECT_MODULE_PLAN.md Fase 3):
 * everybody (non-Viewer) submits, PI reviews, "To Project" creates a project.
 */
class ImprovementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Improvement::class);

        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        $query = Improvement::query()->whereNull('deleted_at');

        if (! app(ImprovementPolicy::class)->seesAll($user)) {
            $query->where('submitted_by_user_id', $user->id);
        }

        $items = $query
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $w->where(fn ($x) => $x->where('title', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhere('department', 'like', $like));
            })
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        $numbers = AssignImprovementNumber::numbersFor($items->pluck('id'));

        return Inertia::render('improvements/index', [
            'items' => $items->map(fn (Improvement $i) => $this->summary($i) + ['number' => $numbers[$i->id] ?? null])->values(),
            'statuses' => Improvement::STATUSES,
            'filters' => ['q' => $q],
            'defaults' => ['department' => (string) $user->department],
            'can' => ['create' => $user->can('create', Improvement::class)],
        ]);
    }

    public function store(SaveImprovementRequest $request, SaveImprovement $action): RedirectResponse
    {
        $improvement = $action(null, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('improvements.toast.submitted')]);

        return to_route('projects.improvements.show', $improvement);
    }

    public function show(Request $request, int $improvement): Response
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        Gate::authorize('view', $improvement);

        $user = $request->user();

        $files = ImprovementFile::where('improvement_id', $improvement->id)->orderByDesc('id')->get();

        $logs = ActivityLog::query()
            ->where('module', 'IMPROVEMENT')
            ->where('record_id', (string) $improvement->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'user_name', 'action', 'created_at']);

        $project = $improvement->project_id
            ? $improvement->project()->whereNull('deleted_at')->first(['id', 'name', 'code'])
            : null;

        return Inertia::render('improvements/show', [
            'improvement' => [
                ...$this->summary($improvement),
                'number' => AssignImprovementNumber::numbersFor([$improvement->id])[$improvement->id] ?? null,
                'background' => $improvement->background,
                'purpose' => $improvement->purpose,
                'goals' => $improvement->goals,
                'issue' => $improvement->issue,
                'saving_mp' => $improvement->saving_mp,
                'quality_metric' => $improvement->quality_metric,
                'links' => $this->linkUrls($improvement->links),
                'pi_owner' => $improvement->pi_owner,
                'pi_feedback' => $improvement->pi_feedback,
                'pi_suggestion' => $improvement->pi_suggestion,
                'pi_action' => $improvement->pi_action,
                'pi_reason' => $improvement->pi_reason,
                'feedback_log' => $this->feedbackLog($improvement->feedback_log),
                'updated_by_name' => $improvement->updated_by_name,
                'after_condition' => $improvement->after_condition,
                'after_result' => $improvement->after_result,
                'after_date' => $improvement->after_date?->toDateString(),
                'after_pic' => $improvement->after_pic,
                'after_links' => collect($improvement->after_links ?? [])
                    ->filter(fn ($l) => is_array($l) && preg_match('#^https?://#i', (string) ($l['url'] ?? '')) === 1)
                    ->map(fn ($l) => ['title' => (string) ($l['title'] ?? ''), 'url' => (string) $l['url']])
                    ->values(),
            ],
            'project' => $project ? ['id' => $project->id, 'name' => $project->name, 'code' => $project->code] : null,
            'validationCase' => $this->linkedCase($improvement),
            'files' => $files->map(fn (ImprovementFile $f) => [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'mime_type' => $f->mime_type,
                'size_bytes' => $f->size_bytes,
                'description' => $f->description,
                'uploaded_at' => $f->uploaded_at?->format('Y-m-d H:i'),
                'is_missing' => $f->is_missing,
                'phase' => $f->phase,
                'url' => route('projects.improvements.files.show', [$improvement->id, $f->id]),
            ])->values(),
            'logs' => $logs->map(fn (ActivityLog $l) => [
                'id' => $l->id,
                'user_name' => $l->user_name,
                'action' => $l->action,
                'created_at' => $l->created_at?->format('Y-m-d H:i'),
            ]),
            'options' => [
                'statuses' => Improvement::STATUSES,
                'decisions' => Improvement::DECISIONS,
            ],
            'can' => [
                'update' => $user->can('update', $improvement),
                'review' => $user->can('review', $improvement),
                'delete' => $user->can('delete', $improvement),
                'attach' => $user->can('update', $improvement) || $user->can('review', $improvement),
                'convert' => $user->can('review', $improvement) && $project === null,
                'close' => $user->can('review', $improvement) && CloseImprovement::isOpen($improvement),
            ],
            'uploadLimitMb' => ProjectOptions::MAX_UPLOAD_BYTES / 1024 / 1024,
        ]);
    }

    public function update(SaveImprovementRequest $request, int $improvement, SaveImprovement $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        $action($improvement, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('improvements.toast.updated')]);

        return to_route('projects.improvements.show', $improvement);
    }

    public function review(ReviewImprovementRequest $request, int $improvement, ReviewImprovement $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        $action($improvement, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('improvements.toast.reviewed')]);

        return to_route('projects.improvements.show', $improvement);
    }

    public function convert(Request $request, int $improvement, ConvertImprovementToProject $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        Gate::authorize('review', $improvement);

        try {
            $project = $action($improvement, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['convert' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('improvements.toast.converted')]);

        return to_route('projects.show', $project);
    }

    public function close(Request $request, int $improvement, CloseImprovement $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        Gate::authorize('review', $improvement);

        if (! CloseImprovement::isOpen($improvement)) {
            return back()->withErrors(['close' => __('improvements.errors.already_final')]);
        }

        $action->done($improvement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('improvements.toast.closed')]);

        return to_route('projects.improvements.show', $improvement);
    }

    public function reject(Request $request, int $improvement, CloseImprovement $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        Gate::authorize('review', $improvement);

        $data = $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        if (! CloseImprovement::isOpen($improvement)) {
            return back()->withErrors(['reason' => __('improvements.errors.already_final')]);
        }

        $action->reject($improvement, $request->user(), $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('improvements.toast.rejected')]);

        return to_route('projects.improvements.show', $improvement);
    }

    public function destroy(Request $request, int $improvement, DeleteImprovement $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        Gate::authorize('delete', $improvement);

        $action($improvement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('improvements.toast.deleted')]);

        return to_route('projects.improvements.index');
    }

    /**
     * The v2.0 Case this Improvement is tied to, if any.
     *
     * @return array{id: int, case_no: string}|null
     */
    private function linkedCase(Improvement $improvement): ?array
    {
        if (! Schema::hasTable('vd_case_links')) {
            return null;
        }

        $caseId = ValidationCaseLink::query()
            ->where('kind', 'improvement')
            ->where('ref_key', (string) $improvement->id)
            ->value('case_id');

        $case = $caseId === null ? null : ValidationCase::query()->whereNull('deleted_at')->where('id', $caseId)->first();

        return $case === null ? null : ['id' => $case->id, 'case_no' => $case->case_no];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Improvement $i): array
    {
        return [
            'id' => $i->id,
            'title' => $i->title,
            'department' => $i->department,
            'type' => $i->type,
            'status' => $i->status,
            'decision' => $i->decision,
            'submitted_by_name' => $i->submitted_by_name,
            'submitted_at' => $i->submitted_at?->format('Y-m-d'),
            'project_id' => $i->project_id,
        ];
    }

    /**
     * Plain list of http(s) URLs. Rows imported before the importer cleaned
     * them hold the old tracker's link objects ({id, url, title, ...}).
     *
     * @param  array<int, mixed>|null  $links
     * @return list<string>
     */
    private function linkUrls(?array $links): array
    {
        $out = [];

        foreach ($links ?? [] as $link) {
            $url = trim((string) (is_array($link) ? ($link['url'] ?? '') : $link));
            if (preg_match('#^https?://#i', $url) === 1) {
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * Normalise feedback entries to one shape. Entries written by this app
     * are `{at: 'Y-m-d H:i:s', by, status, decision, text}`; entries imported
     * from Project Tracking carry `at` as epoch milliseconds and keep the
     * message in `feedback` (plus an optional `action` note).
     *
     * @param  array<int, mixed>|null  $log
     * @return list<array{at: string, by: string, status: string, decision: string, text: string}>
     */
    private function feedbackLog(?array $log): array
    {
        $out = [];

        foreach ($log ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $at = $entry['at'] ?? '';
            if (is_int($at) || is_float($at) || (is_string($at) && ctype_digit($at))) {
                $at = Carbon::createFromTimestampMs((int) $at)
                    ->setTimezone((string) config('app.timezone'))
                    ->format('Y-m-d H:i');
            }

            $text = trim((string) ($entry['text'] ?? $entry['feedback'] ?? ''));
            $action = trim((string) ($entry['action'] ?? ''));
            if ($action !== '' && ! array_key_exists('text', $entry)) {
                $text = trim($text."\n".$action);
            }

            $out[] = [
                'at' => (string) $at,
                'by' => (string) ($entry['by'] ?? ''),
                'status' => (string) ($entry['status'] ?? ''),
                'decision' => (string) ($entry['decision'] ?? ''),
                'text' => $text,
            ];
        }

        return $out;
    }
}
