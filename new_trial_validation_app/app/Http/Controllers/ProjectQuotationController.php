<?php

namespace App\Http\Controllers;

use App\Actions\Projects\DeleteProjectQuotation;
use App\Actions\Projects\SaveProjectQuotation;
use App\Http\Requests\Projects\SaveProjectQuotationRequest;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Support\ProjectOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quotation & PO. The cross-project list lives here (`projects.quotations.index`);
 * creating/editing happens inside a project's detail page (K3 default: no
 * budget/actual UI yet, only quotations).
 */
class ProjectQuotationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'vendor' => (string) $request->query('vendor', ''),
        ];

        $items = ProjectQuotation::query()
            ->join('pm_projects', 'pm_projects.id', '=', 'pm_quotations.project_id')
            ->whereNull('pm_projects.deleted_at')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $query->where(fn ($w) => $w->where('pm_quotations.vendor_name', 'like', $like)
                    ->orWhere('pm_quotations.quotation_no', 'like', $like)
                    ->orWhere('pm_projects.name', 'like', $like)
                    ->orWhere('pm_projects.code', 'like', $like));
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('pm_quotations.status', $filters['status']))
            ->when($filters['vendor'] !== '', fn ($query) => $query->where('pm_quotations.vendor_name', $filters['vendor']))
            ->orderByDesc('pm_quotations.id')
            ->select('pm_quotations.*', 'pm_projects.name as project_name', 'pm_projects.code as project_code')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProjectQuotation $q) => [
                'id' => $q->id,
                'project_id' => $q->project_id,
                'project_name' => $q->getAttribute('project_name'),
                'project_code' => $q->getAttribute('project_code'),
                'vendor_name' => $q->vendor_name,
                'quotation_no' => $q->quotation_no,
                'amount' => (float) $q->amount,
                'currency' => $q->currency,
                'quotation_date' => $q->quotation_date?->format('Y-m-d'),
                'valid_until' => $q->valid_until?->format('Y-m-d'),
                'status' => $q->status,
                'has_file' => $q->file_id !== null,
            ]);

        return Inertia::render('projects/quotations', [
            'items' => $items,
            'filters' => $filters,
            'options' => [
                'statuses' => ProjectOptions::QUOTATION_STATUSES,
                'vendors' => ProjectQuotation::query()->where('vendor_name', '!=', '')->distinct()->orderBy('vendor_name')->pluck('vendor_name'),
            ],
        ]);
    }

    public function store(SaveProjectQuotationRequest $request, int $project, SaveProjectQuotation $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        $action($project, null, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.quotation_saved')]);

        return to_route('projects.show', $project);
    }

    public function update(SaveProjectQuotationRequest $request, int $project, int $quotation, SaveProjectQuotation $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $quotation = ProjectQuotation::where('project_id', $project->id)->findOrFail($quotation);

        $action($project, $quotation, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.quotation_saved')]);

        return to_route('projects.show', $project);
    }

    public function destroy(Request $request, int $project, int $quotation, DeleteProjectQuotation $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $quotation = ProjectQuotation::where('project_id', $project->id)->findOrFail($quotation);

        Gate::authorize('update', $project);

        $action($project, $quotation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.quotation_deleted')]);

        return to_route('projects.show', $project);
    }
}
