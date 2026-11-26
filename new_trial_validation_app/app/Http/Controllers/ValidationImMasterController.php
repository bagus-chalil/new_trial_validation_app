<?php

namespace App\Http\Controllers;

use App\Actions\ImMaster\CreateImMaster;
use App\Actions\ImMaster\CreateImRevision;
use App\Models\ImBrandCode;
use App\Models\ImMaster;
use App\Models\ImNumber;
use App\Models\ImRevision;
use App\Models\Product;
use App\Models\ValidationCase;
use App\Services\Validation\ValidationRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trial Validation v2.0 Tahap 6: IM master list (one per FG Code) and the
 * start of a master / of a new revision. The worksheet, sign-off chain and PDF
 * live in ValidationImRevisionController. QA owns everything here.
 */
class ValidationImMasterController extends Controller
{
    public function index(Request $request): Response
    {
        $ready = Schema::hasTable('vd_im_masters') && Schema::hasTable('vd_im_revisions');
        $canCreate = Gate::allows('create', ImRevision::class);

        $f = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $paginator = null;
        $rows = collect();

        if ($ready) {
            $paginator = ImMaster::query()
                ->when($f['q'] !== '', function ($q) use ($f) {
                    $like = '%'.addcslashes($f['q'], '%_\\').'%';
                    $q->where(fn ($w) => $w->where('fg_code', 'like', $like)
                        ->orWhere('product_name', 'like', $like)
                        ->orWhere('product_part', 'like', $like)
                        ->orWhere('customer', 'like', $like));
                })
                ->when(in_array($f['status'], ImRevision::STATUSES, true), fn ($q) => $q->whereIn(
                    'id',
                    ImRevision::query()->where('status', $f['status'])->select('master_id'),
                ))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();

            $revisions = ImRevision::query()
                ->whereIn('master_id', $paginator->getCollection()->pluck('id'))
                ->orderByDesc('revision_no')
                ->get()
                ->groupBy('master_id');

            $rows = $paginator->getCollection()->map(function (ImMaster $m) use ($revisions) {
                $all = $revisions->get($m->id, collect());
                $latest = $all->first();
                $effective = $all->firstWhere('status', 'Effective');

                return [
                    'id' => $m->id,
                    'fg_code' => $m->fg_code,
                    'product_name' => $m->product_name !== '' ? $m->product_name : $m->product_part,
                    'customer' => $m->customer,
                    'revisions_count' => $all->count(),
                    'latest' => $latest === null ? null : [
                        'id' => $latest->id, 'im_number' => $latest->im_number, 'revision_no' => $latest->revision_no, 'status' => $latest->status,
                        'pending_with' => $latest->pending_with,
                    ],
                    'effective' => $effective === null ? null : [
                        'id' => $effective->id, 'im_number' => $effective->im_number, 'revision_no' => $effective->revision_no,
                        'effective_from' => $effective->effective_from?->toDateString(),
                    ],
                ];
            });
        }

        return Inertia::render('validation/im-masters/index', [
            'masters' => [
                'data' => $rows->values(),
                'current_page' => $paginator?->currentPage() ?? 1,
                'last_page' => $paginator?->lastPage() ?? 1,
                'per_page' => $paginator?->perPage() ?? 15,
                'total' => $paginator?->total() ?? 0,
                'links' => $paginator?->linkCollection()->all() ?? [],
            ],
            'filters' => $f,
            'statuses' => ImRevision::STATUSES,
            'ready' => $ready,
            'can' => ['create' => $canCreate],
            // Arrives from the IM numbers page: open the create dialog with this issued number preselected.
            'prefillNumberId' => $canCreate && $request->filled('number') ? (int) $request->query('number') : null,
            'brands' => $ready && $canCreate
                ? ImBrandCode::query()->where('is_active', true)->orderBy('code')->get(['code', 'name'])->map(fn (ImBrandCode $b) => ['code' => $b->code, 'name' => $b->name])->values()
                : [],
            'products' => $ready && $canCreate
                ? Product::query()->whereNull('deleted_at')->where('is_active', true)->orderBy('product_name')->limit(1000)->get(['id', 'product_name', 'finish_good_code'])
                    ->map(fn (Product $p) => ['id' => $p->id, 'label' => trim($p->product_name.' · '.$p->finish_good_code, ' ·'), 'fg' => $p->finish_good_code])->values()
                : [],
            'issuedNumbers' => $ready && $canCreate
                ? ImNumber::query()->where('status', 'Active')
                    ->whereNotIn('id', ImRevision::query()->whereNotNull('im_number_id')->select('im_number_id'))
                    ->orderBy('number')->limit(300)->get(['id', 'number', 'fg_codes'])
                    ->map(fn (ImNumber $n) => ['id' => $n->id, 'number' => $n->number, 'fg' => ($n->fg_codes ?? [])[0] ?? ''])->values()
                : [],
        ]);
    }

    public function store(Request $request, CreateImMaster $action): RedirectResponse
    {
        Gate::authorize('create', ImRevision::class);

        $data = $request->validate([
            'fg_code' => ['required', 'string', 'max:60'],
            'existing_im_number_id' => ['nullable', 'integer'],
            'brand_code' => ['required_without:existing_im_number_id', 'nullable', 'string', 'max:10'],
            'product_part' => ['required_without:existing_im_number_id', 'nullable', 'string', 'max:200'],
            'product_id' => ['nullable', 'integer'],
            'customer' => ['nullable', 'string', 'max:150'],
            'legacy_reference' => ['nullable', 'string', 'max:255'],
            'case_id' => ['nullable', 'integer'],
        ]);

        $revision = $action($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.master_created', ['number' => $revision->im_number])]);

        return to_route('validation.im-revisions.show', $revision->id);
    }

    public function show(Request $request, int $master): Response
    {
        $master = ImMaster::query()->findOrFail($master);
        $revisions = $master->revisions()->get();

        // A master is readable by everyone only through its Effective / Superseded revisions.
        $visible = $revisions->filter(fn (ImRevision $r) => Gate::allows('view', $r))->values();
        abort_if($visible->isEmpty(), 403);

        $hasOpen = $revisions->contains(fn (ImRevision $r) => in_array($r->status, ImRevision::OPEN, true));

        return Inertia::render('validation/im-masters/show', [
            'master' => [
                'id' => $master->id, 'fg_code' => $master->fg_code, 'brand_code' => $master->brand_code,
                'product_part' => $master->product_part, 'product_name' => $master->product_name,
                'customer' => $master->customer, 'bulk_wip_refs' => $master->bulk_wip_refs ?? [],
            ],
            'revisions' => $visible->map(fn (ImRevision $r) => [
                'id' => $r->id, 'revision_no' => $r->revision_no, 'im_number' => $r->im_number, 'status' => $r->status,
                'issue_date' => $r->issue_date?->toDateString(), 'effective_from' => $r->effective_from?->toDateString(),
                'revision_reason' => $r->revision_reason, 'replaces_number' => $r->replaces_number,
                'prepared_by_name' => $r->prepared_by_name, 'approved_by' => $r->approved_by,
                'pending_with' => $r->pending_with, 'is_onboarded' => $r->is_onboarded,
            ])->values(),
            'can' => ['revise' => ValidationRoles::isQaEditor($request->user()) && ! $hasOpen],
            'hasOpen' => $hasOpen,
        ]);
    }

    public function revisionStore(Request $request, int $master, CreateImRevision $action): RedirectResponse
    {
        Gate::authorize('create', ImRevision::class);

        $master = ImMaster::query()->findOrFail($master);
        $data = $request->validate(['revision_reason' => ['required', 'string', 'max:255']]);

        $revision = $action($master, $data['revision_reason'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.revision_created', ['number' => $revision->im_number])]);

        return to_route('validation.im-revisions.show', $revision->id);
    }

    /**
     * Cases that can be linked to the IM number (same product).
     *
     * @return list<array{id: int, label: string}>
     */
    public static function caseOptions(ImMaster $master): array
    {
        if ($master->product_id === null) {
            return [];
        }

        return array_values(ValidationCase::query()->where('product_id', $master->product_id)->orderByDesc('id')->limit(50)->get(['id', 'case_no', 'product_name'])
            ->map(fn (ValidationCase $c) => ['id' => $c->id, 'label' => $c->case_no.' · '.$c->product_name])->all());
    }
}
