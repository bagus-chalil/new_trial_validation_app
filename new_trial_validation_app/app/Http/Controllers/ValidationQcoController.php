<?php

namespace App\Http\Controllers;

use App\Actions\Validation\IssueQcoNumber;
use App\Models\Product;
use App\Models\QcoNumber;
use App\Models\ValidationCaseLink;
use App\Services\Validation\ValidationRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trial Validation v2.0 Tahap 3: QCO number register. PRNI issues the next
 * number directly (no approval step); everybody logged in reads. A void number
 * is kept and never reused.
 */
class ValidationQcoController extends Controller
{
    public function index(Request $request): Response
    {
        $f = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $ready = Schema::hasTable('vd_qco_numbers');

        $rows = collect();
        $paginator = null;
        if ($ready) {
            $paginator = QcoNumber::query()
                ->when($f['q'] !== '', function ($q) use ($f) {
                    $like = '%'.addcslashes($f['q'], '%_\\').'%';
                    $q->where(fn ($w) => $w->where('number', 'like', $like)
                        ->orWhere('product_name', 'like', $like)
                        ->orWhere('client', 'like', $like));
                })
                ->when(in_array($f['status'], ['Issued', 'Void'], true), fn ($q) => $q->where('status', $f['status']))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();

            $cases = ValidationCaseLink::query()
                ->where('kind', 'qco')
                ->whereIn('ref_key', $paginator->getCollection()->pluck('number'))
                ->with('validationCase:id,case_no')
                ->get()
                ->keyBy('ref_key');

            $rows = $paginator->getCollection()->map(fn (QcoNumber $n) => [
                'id' => $n->id,
                'number' => $n->number,
                'product_name' => $n->product_name,
                'client' => $n->client,
                'sales_month' => $n->sales_month,
                'qty_po' => $n->qty_po,
                'notes' => $n->notes,
                'status' => $n->status,
                'void_reason' => $n->void_reason,
                'issued_by_name' => $n->issued_by_name,
                'issued_at' => $n->created_at?->toDateString(),
                'case_id' => $cases->get($n->number)?->case_id,
                'case_no' => $cases->get($n->number)?->validationCase?->case_no,
            ]);
        }

        $canIssue = ValidationRoles::isPrni($request->user());

        return Inertia::render('validation/qco/index', [
            'numbers' => [
                'data' => $rows->values(),
                'current_page' => $paginator?->currentPage() ?? 1,
                'last_page' => $paginator?->lastPage() ?? 1,
                'per_page' => $paginator?->perPage() ?? 15,
                'total' => $paginator?->total() ?? 0,
                'links' => $paginator?->linkCollection()->all() ?? [],
            ],
            'filters' => $f,
            'products' => $canIssue
                ? Product::query()->whereNull('deleted_at')->where('is_active', true)->orderBy('product_name')->limit(1000)->get(['id', 'product_name', 'finish_good_code'])
                    ->map(fn (Product $p) => ['id' => $p->id, 'label' => trim($p->product_name.' · '.$p->finish_good_code, ' ·')])->values()
                : [],
            'ready' => $ready,
            'can' => ['issue' => $canIssue],
        ]);
    }

    public function store(Request $request, IssueQcoNumber $action): RedirectResponse
    {
        abort_unless(ValidationRoles::isPrni($request->user()), 403);

        $data = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'client' => ['required', 'string', 'max:150'],
            'sales_month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'qty_po' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['product_id']) && trim((string) ($data['product_name'] ?? '')) === '') {
            return back()->withErrors(['product_name' => __('numbers.qco.product_required')]);
        }

        $qco = $action->issue($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.qco_issued', ['number' => $qco->number])]);

        return back();
    }

    public function void(Request $request, int $qco, IssueQcoNumber $action): RedirectResponse
    {
        abort_unless(ValidationRoles::isPrni($request->user()), 403);

        $model = QcoNumber::query()->findOrFail($qco);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $action->void($model, $data['reason'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.number_void')]);

        return back();
    }
}
