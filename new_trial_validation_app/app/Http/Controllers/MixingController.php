<?php

namespace App\Http\Controllers;

use App\Actions\Mixing\SaveMixingHeader;
use App\Models\MasterOption;
use App\Models\Mixing;
use App\Models\Product;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationParameter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trial Validation v2.0 Tahap 4: Mixing list, header form and the read/act
 * summary page. The wizard steps (validation, attachments, review) are in
 * MixingStepController. Own vd_mixings table, never trials_header.
 */
class MixingController extends Controller
{
    public const STATUSES = ['Draft', 'In Review', 'Ready for Approval', 'Approved', 'Need Revision', 'Rejected'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $user = $request->user();

        $rows = Mixing::query()
            ->whereNull('deleted_at')
            // Drafts only for their owner / Admin.
            ->where(function ($w) use ($user) {
                $w->where('progress_status', '!=', 'Draft');
                $w->orWhere('created_by', $user->email);
                if ($user->isAdmin()) {
                    $w->orWhereRaw('1 = 1');
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('mixing_code', 'like', $like)
                    ->orWhere('product_name', 'like', $like)
                    ->orWhere('batch_no', 'like', $like));
            })
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('progress_status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('mixing/index', [
            'mixings' => [
                'data' => $rows->getCollection()->map(fn (Mixing $m) => $this->row($m))->values(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'links' => $rows->linkCollection()->all(),
            ],
            'filters' => ['q' => $q, 'status' => $status],
            'statuses' => self::STATUSES,
            'canCreate' => Gate::forUser($user)->allows('create', Mixing::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Mixing::class);

        $case = $request->filled('case')
            ? ValidationCase::query()->whereNull('deleted_at')->find((int) $request->query('case'))
            : null;

        return Inertia::render('mixing/form', [
            'mixing' => null,
            'caseId' => $case?->id,
            'caseNo' => $case?->case_no,
            'defaultProductId' => $case?->product_id,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request, SaveMixingHeader $action): RedirectResponse
    {
        Gate::authorize('create', Mixing::class);

        $case = $request->filled('case_id')
            ? ValidationCase::query()->whereNull('deleted_at')->findOrFail((int) $request->input('case_id'))
            : null;

        $mixing = $action->create($this->validated($request), $request->user(), $case);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.saved')]);

        return to_route('mixings.validation.edit', $mixing->id);
    }

    public function edit(int $mixing): Response
    {
        $mixing = Mixing::query()->whereNull('deleted_at')->findOrFail($mixing);
        Gate::authorize('view', $mixing);

        return Inertia::render('mixing/form', [
            'mixing' => $this->detail($mixing),
            'caseId' => $mixing->case_id,
            'caseNo' => ValidationCase::query()->whereKey($mixing->case_id)->value('case_no'),
            'defaultProductId' => null,
            'canEdit' => Gate::allows('update', $mixing),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, int $mixing, SaveMixingHeader $action): RedirectResponse
    {
        $mixing = Mixing::query()->whereNull('deleted_at')->findOrFail($mixing);
        Gate::authorize('update', $mixing);

        $action->update($mixing, $this->validated($request), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.saved')]);

        return to_route('mixings.validation.edit', $mixing->id);
    }

    public function destroy(Request $request, int $mixing): RedirectResponse
    {
        $mixing = Mixing::query()->whereNull('deleted_at')->findOrFail($mixing);
        Gate::authorize('delete', $mixing);

        $mixing->update(['deleted_at' => now(), 'deleted_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.deleted')]);

        return to_route('mixings.index');
    }

    public function show(Request $request, int $mixing): Response
    {
        $mixing = Mixing::query()->whereNull('deleted_at')->findOrFail($mixing);
        Gate::authorize('view', $mixing);

        $user = $request->user();
        $parameters = ValidationParameter::query()
            ->whereIn('id', $mixing->results()->pluck('parameter_id'))
            ->get(['id', 'parameter_name', 'specification'])
            ->keyBy('id');

        $round = $mixing->currentReviewRound();
        $reviews = $mixing->reviews()->orderBy('review_round')->orderBy('department')->get();
        $myPending = $reviews
            ->filter(fn ($r) => (int) $r->review_round === $round && Gate::forUser($user)->allows('review', $r))
            ->map(fn ($r) => ['id' => $r->id, 'department' => $r->department])
            ->values();

        return Inertia::render('mixing/show', [
            'mixing' => $this->detail($mixing),
            'caseNo' => ValidationCase::query()->whereKey($mixing->case_id)->value('case_no'),
            'results' => $mixing->results()->get()->map(fn ($r) => [
                'parameter_name' => $parameters[$r->parameter_id]->parameter_name ?? '#'.$r->parameter_id,
                'specification' => $parameters[$r->parameter_id]->specification ?? null,
                'decision' => $r->decision,
                'result_value' => $r->result_value,
                'remark' => $r->remark,
            ])->values(),
            'files' => $mixing->files()->orderBy('category')->orderBy('id')->get()->map(fn ($f) => [
                'id' => $f->id,
                'category' => $f->category,
                'original_name' => $f->original_name,
                'mime' => $f->mime,
                'caption' => $f->caption,
            ])->values(),
            'reviews' => $reviews->map(fn ($r) => [
                'department' => $r->department,
                'review_round' => $r->review_round,
                'status' => $r->status,
                'reviewer_name' => $r->reviewer_name,
                'comment' => $r->comment,
                'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
            ])->values(),
            'myPendingReviews' => $myPending,
            'canApprove' => Gate::forUser($user)->allows('approve', $mixing),
            'canEdit' => Gate::forUser($user)->allows('update', $mixing),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'product_type' => ['required', 'string', 'max:100', Rule::exists('master_options', 'name')->where('type', 'product_type')->where('is_active', 1)],
            'batch_no' => ['required', 'string', 'max:100'],
            'batch_size_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'mixing_machine' => ['nullable', 'string', 'max:150'],
            'mixing_date' => ['required', 'date'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'batch_record_no' => ['nullable', 'string', 'max:100'],
            'batch_record_revision' => ['nullable', 'string', 'max:50'],
            'revision_summary' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'products' => Product::query()->where('is_active', 1)->whereNull('deleted_at')
                ->orderBy('product_name')->get(['id', 'product_name', 'finish_good_code'])
                ->map(fn (Product $p) => ['value' => (string) $p->id, 'label' => $p->product_name.($p->finish_good_code ? ' ('.$p->finish_good_code.')' : '')])
                ->values(),
            'productTypes' => MasterOption::query()->where('type', 'product_type')->where('is_active', 1)
                ->orderBy('sort_order')->orderBy('name')->pluck('name')->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(Mixing $m): array
    {
        return [
            'id' => $m->id,
            'mixing_code' => $m->mixing_code,
            'product_name' => $m->product_name,
            'product_type' => $m->product_type,
            'batch_no' => $m->batch_no,
            'mixing_date' => $m->mixing_date?->toDateString(),
            'progress_status' => $m->progress_status,
            'pending_with' => $m->pending_with,
            'case_id' => $m->case_id,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Mixing $m): array
    {
        $approver = $m->approver_user_id ? User::query()->whereKey($m->approver_user_id)->value('name') : null;

        return [
            ...$this->row($m),
            'product_id' => $m->product_id,
            'current_step' => $m->current_step,
            'batch_size_kg' => $m->batch_size_kg,
            'mixing_machine' => $m->mixing_machine,
            'objective' => $m->objective,
            'batch_record_no' => $m->batch_record_no,
            'batch_record_revision' => $m->batch_record_revision,
            'revision_summary' => $m->revision_summary,
            'revision_no' => $m->revision_no,
            'final_decision' => $m->final_decision,
            'approved_by' => $m->approved_by,
            'approved_at' => $m->approved_at?->toDateTimeString(),
            'rejected_by' => $m->rejected_by,
            'approval_comment' => $m->approval_comment,
            'approver_name' => $approver,
            'files_count' => $m->files()->count(),
        ];
    }
}
