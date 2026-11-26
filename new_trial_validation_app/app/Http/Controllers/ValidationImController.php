<?php

namespace App\Http\Controllers;

use App\Actions\Validation\IssueImNumber;
use App\Models\ImBrandCode;
use App\Models\ImNumber;
use App\Models\ImRevision;
use App\Models\Product;
use App\Models\ValidationCaseLink;
use App\Services\Validation\ValidationRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trial Validation v2.0 Tahap 3: IM (Instruksi Mutu) number register. QA issues
 * IM-{brand}-{product}[ V{n}] directly (no approval step, the paper IM keeps
 * its own sign-off chain) and revises it to the next version. Everybody logged
 * in reads. A void number is kept and never reused.
 */
class ValidationImController extends Controller
{
    public function index(Request $request): Response
    {
        $f = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $ready = Schema::hasTable('vd_im_numbers') && Schema::hasTable('vd_im_brand_codes');
        $canIssue = ValidationRoles::isQa($request->user());

        $rows = collect();
        $paginator = null;
        if ($ready) {
            $paginator = ImNumber::query()
                ->when($f['q'] !== '', function ($q) use ($f) {
                    $like = '%'.addcslashes($f['q'], '%_\\').'%';
                    $q->where(fn ($w) => $w->where('number', 'like', $like)
                        ->orWhere('customer', 'like', $like)
                        ->orWhere('fg_codes', 'like', $like));
                })
                ->when(in_array($f['status'], ImNumber::STATUSES, true), fn ($q) => $q->where('status', $f['status']))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();

            $cases = ValidationCaseLink::query()
                ->where('kind', 'im')
                ->whereIn('ref_key', $paginator->getCollection()->pluck('number'))
                ->with('validationCase:id,case_no')
                ->get()
                ->keyBy('ref_key');

            $rows = $paginator->getCollection()->map(fn (ImNumber $n) => [
                'id' => $n->id,
                'number' => $n->number,
                'brand_code' => $n->brand_code,
                'version_no' => $n->version_no,
                'replaces_number' => $n->replaces_number,
                'revision_reason' => $n->revision_reason,
                'issue_date' => $n->issue_date?->toDateString(),
                'fg_codes' => $n->fg_codes ?? [],
                'line_machine' => $n->line_machine,
                'customer' => $n->customer,
                'customer_ref' => $n->customer_ref,
                'status' => $n->status,
                'void_reason' => $n->void_reason,
                'issued_by_name' => $n->issued_by_name,
                'case_id' => $cases->get($n->number)?->case_id,
                'case_no' => $cases->get($n->number)?->validationCase?->case_no,
            ]);
        }

        return Inertia::render('validation/im/index', [
            'numbers' => [
                'data' => $rows->values(),
                'current_page' => $paginator?->currentPage() ?? 1,
                'last_page' => $paginator?->lastPage() ?? 1,
                'per_page' => $paginator?->perPage() ?? 15,
                'total' => $paginator?->total() ?? 0,
                'links' => $paginator?->linkCollection()->all() ?? [],
            ],
            'filters' => $f,
            'statuses' => ImNumber::STATUSES,
            'brands' => $ready
                ? ImBrandCode::query()->where('is_active', true)->orderBy('code')->get(['code', 'name'])->map(fn (ImBrandCode $b) => ['code' => $b->code, 'name' => $b->name])->values()
                : [],
            'products' => $canIssue
                ? Product::query()->whereNull('deleted_at')->where('is_active', true)->orderBy('product_name')->limit(1000)->get(['id', 'product_name', 'finish_good_code'])
                    ->map(fn (Product $p) => ['id' => $p->id, 'label' => trim($p->product_name.' · '.$p->finish_good_code, ' ·'), 'name' => $p->product_name, 'fg' => $p->finish_good_code])->values()
                : [],
            'ready' => $ready,
            'can' => ['issue' => $canIssue],
        ]);
    }

    public function store(Request $request, IssueImNumber $action): RedirectResponse
    {
        abort_unless(ValidationRoles::isQa($request->user()), 403);

        $data = $request->validate([
            'brand_code' => ['required', 'string', 'max:10'],
            'product_part' => ['required', 'string', 'max:200'],
            'product_id' => ['nullable', 'integer'],
            'fg_codes' => ['nullable', 'array'],
            'fg_codes.*' => ['string', 'max:60'],
            'line_machine' => ['nullable', 'string', 'max:200'],
            'customer' => ['nullable', 'string', 'max:150'],
            'customer_ref' => ['nullable', 'string', 'max:150'],
            'issue_date' => ['nullable', 'date'],
        ]);

        $im = $action->issue($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.im_issued', ['number' => $im->number])]);

        return back();
    }

    public function revise(Request $request, int $im, IssueImNumber $action): RedirectResponse
    {
        abort_unless(ValidationRoles::isQa($request->user()), 403);

        $previous = ImNumber::query()->findOrFail($im);
        $this->assertNotManaged($previous);

        $data = $request->validate([
            'revision_reason' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'fg_codes' => ['nullable', 'array'],
            'fg_codes.*' => ['string', 'max:60'],
            'line_machine' => ['nullable', 'string', 'max:200'],
        ]);

        $new = $action->revise($previous, array_filter($data, fn ($v) => $v !== null), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.im_issued', ['number' => $new->number])]);

        return back();
    }

    public function void(Request $request, int $im, IssueImNumber $action): RedirectResponse
    {
        abort_unless(ValidationRoles::isQa($request->user()), 403);

        $model = ImNumber::query()->findOrFail($im);
        $this->assertNotManaged($model);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $action->void($model, $data['reason'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.number_void')]);

        return back();
    }

    /** Numbers owned by an IM master move only through its revision workflow (QA approval). */
    private function assertNotManaged(ImNumber $number): void
    {
        if (Schema::hasTable('vd_im_revisions') && ImRevision::query()->where('im_number_id', $number->id)->exists()) {
            abort(422, __('im_master.error.managed_number'));
        }
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        abort_unless(ValidationRoles::isQa($request->user()), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('vd_im_brand_codes', 'code')],
            'name' => ['required', 'string', 'max:150'],
        ]);

        ImBrandCode::create(['code' => strtoupper($data['code']), 'name' => trim($data['name']), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.brand_added')]);

        return back();
    }
}
