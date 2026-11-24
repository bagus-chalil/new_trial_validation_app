<?php

namespace App\Http\Controllers;

use App\Actions\Projects\DeletePurchaseOrder;
use App\Actions\Projects\SavePurchaseOrder;
use App\Http\Requests\Projects\SavePurchaseOrderRequest;
use App\Http\Requests\Projects\SavePurchaseOrderTemplateRequest;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Services\Pdf\PdfService;
use App\Services\Projects\PurchaseOrderNumbering;
use App\Support\PurchaseOrderSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchase Order documents (FR.PRC.001.00) of the Projects module: a PO belongs
 * to one project, is numbered from a template pattern, and is printed as a PDF.
 * The cross-project list lives here too (`projects.purchase-orders.index`).
 */
class ProjectPurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'vendor' => (string) $request->query('vendor', ''),
        ];

        $items = PurchaseOrder::query()
            ->join('pm_projects', 'pm_projects.id', '=', 'pm_purchase_orders.project_id')
            ->whereNull('pm_projects.deleted_at')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $query->where(fn ($w) => $w->where('pm_purchase_orders.po_no', 'like', $like)
                    ->orWhere('pm_purchase_orders.vendor_name', 'like', $like)
                    ->orWhere('pm_purchase_orders.subject', 'like', $like)
                    ->orWhere('pm_projects.name', 'like', $like)
                    ->orWhere('pm_projects.code', 'like', $like));
            })
            ->when($filters['vendor'] !== '', fn ($query) => $query->where('pm_purchase_orders.vendor_name', $filters['vendor']))
            ->orderByDesc('pm_purchase_orders.po_date')
            ->orderByDesc('pm_purchase_orders.id')
            ->select('pm_purchase_orders.*', 'pm_projects.name as project_name', 'pm_projects.code as project_code')
            ->with('items')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'project_id' => $po->project_id,
                'project_name' => $po->getAttribute('project_name'),
                'project_code' => $po->getAttribute('project_code'),
                'po_no' => $po->po_no,
                'po_date' => $po->po_date?->format('Y-m-d'),
                'vendor_name' => $po->vendor_name,
                'subject' => $po->subject,
                'currency' => $po->currency,
                'total' => $po->totals()['total'],
            ]);

        return Inertia::render('projects/purchase-orders/index', [
            'items' => $items,
            'filters' => $filters,
            'vendors' => PurchaseOrder::query()->where('vendor_name', '!=', '')->distinct()->orderBy('vendor_name')->pluck('vendor_name'),
            'canCreate' => (bool) $request->user()?->can('create', PurchaseOrder::class),
        ]);
    }

    public function create(Request $request, int $project, PurchaseOrderNumbering $numbering): Response
    {
        Gate::authorize('create', PurchaseOrder::class);
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        $template = PurchaseOrderSettings::get();
        $today = Carbon::today();

        $quotation = null;
        $contact = null;
        if ($request->filled('quotation')) {
            $quotation = ProjectQuotation::query()
                ->where('project_id', $project->id)
                ->where('id', (int) $request->query('quotation'))
                ->first();
        }
        if ($quotation !== null) {
            $contact = VendorContact::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($quotation->vendor_name)])->first();
        }

        return Inertia::render('projects/purchase-orders/form', [
            'project' => $this->projectSummary($project),
            'order' => [
                'id' => null,
                'po_no' => $numbering->next($today),
                'po_date' => $today->format('Y-m-d'),
                'quotation_id' => $quotation?->id,
                'vendor_name' => $quotation->vendor_name ?? '',
                'currency' => $quotation->currency ?? 'IDR',
                'vendor_address' => $contact->address ?? '',
                'attn' => $contact->contact ?? '',
                'tel' => $contact->phone ?? '',
                'email' => $contact->email ?? '',
                'quotation_no' => $quotation->quotation_no ?? '',
                'subject' => $project->name,
                'tax_percent' => (float) $template['tax'],
                'terms' => $template['terms'],
                'delivery' => $template['delivery'],
                'lead_time' => $template['lead_time'],
                'note' => '',
                'ship_name' => $template['ship_name'],
                'ship_address' => $template['ship_address'],
                'ship_tel' => $template['ship_tel'],
                'ship_attn' => $project->pic_name,
                'use_signature' => false,
                'items' => [[
                    'description' => '',
                    'qty' => 1,
                    'unit' => 'Unit',
                    'unit_price' => $quotation ? (float) $quotation->amount : 0,
                    'due_date' => null,
                ]],
            ],
            'options' => $this->formOptions($project),
        ]);
    }

    public function store(SavePurchaseOrderRequest $request, int $project, SavePurchaseOrder $action): RedirectResponse
    {
        $project = $request->project();

        $order = $action($project, null, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('purchase_orders.toast.saved')]);

        return to_route('projects.purchase-orders.show', $order);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('view', $purchaseOrder);
        $project = Project::whereNull('deleted_at')->findOrFail($purchaseOrder->project_id);
        $purchaseOrder->load('items');
        $template = PurchaseOrderSettings::get();

        return Inertia::render('projects/purchase-orders/show', [
            'project' => $this->projectSummary($project),
            'order' => $this->orderPayload($purchaseOrder),
            'totals' => $purchaseOrder->totals(),
            'documentLanguage' => $template['lang'],
            'canEdit' => (bool) $request->user()?->can('update', $purchaseOrder),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('update', $purchaseOrder);
        $project = Project::whereNull('deleted_at')->findOrFail($purchaseOrder->project_id);
        $purchaseOrder->load('items');

        return Inertia::render('projects/purchase-orders/form', [
            'project' => $this->projectSummary($project),
            'order' => $this->orderPayload($purchaseOrder),
            'options' => $this->formOptions($project),
        ]);
    }

    public function update(SavePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, SavePurchaseOrder $action): RedirectResponse
    {
        $order = $action($request->project(), $purchaseOrder, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('purchase_orders.toast.saved')]);

        return to_route('projects.purchase-orders.show', $order);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, DeletePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('delete', $purchaseOrder);
        $project = Project::whereNull('deleted_at')->findOrFail($purchaseOrder->project_id);

        $action($project, $purchaseOrder, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('purchase_orders.toast.deleted')]);

        return to_route('projects.show', $project);
    }

    public function pdf(PurchaseOrder $purchaseOrder, PdfService $pdf): HttpResponse
    {
        Gate::authorize('view', $purchaseOrder);
        $purchaseOrder->load('items');

        $template = PurchaseOrderSettings::get();
        $lang = $template['lang'];

        return $pdf->fromView('pdf.purchase-order', [
            'po' => $purchaseOrder,
            'totals' => $purchaseOrder->totals(),
            't' => $template,
            'lb' => PurchaseOrderSettings::labels($lang),
            'dateFmt' => fn (?CarbonInterface $date) => PurchaseOrderSettings::formatDate($date, $template['date_format'], $lang),
            'numberFmt' => fn (float $n) => number_format($n, 0, $lang === 'en' ? '.' : ',', $lang === 'en' ? ',' : '.'),
        ], 'PO-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $purchaseOrder->po_no).'.pdf');
    }

    public function template(): Response
    {
        Gate::authorize('manage-projects');

        return Inertia::render('projects/purchase-orders/template', [
            'template' => PurchaseOrderSettings::get(),
            'dateFormats' => PurchaseOrderSettings::DATE_FORMATS,
            'languages' => PurchaseOrderSettings::LANGUAGES,
            'preview' => [
                'number' => app(PurchaseOrderNumbering::class)->next(Carbon::today()),
            ],
        ]);
    }

    public function updateTemplate(SavePurchaseOrderTemplateRequest $request): RedirectResponse
    {
        PurchaseOrderSettings::save($request->templateData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('purchase_orders.toast.template_saved')]);

        return to_route('projects.purchase-orders.template');
    }

    /** @return array<string, mixed> */
    private function projectSummary(Project $project): array
    {
        return ['id' => $project->id, 'code' => $project->code, 'name' => $project->name, 'pic_name' => $project->pic_name];
    }

    /** @return array<string, mixed> */
    private function formOptions(Project $project): array
    {
        return [
            'quotations' => ProjectQuotation::query()->where('project_id', $project->id)->orderByDesc('id')
                ->get(['id', 'vendor_name', 'quotation_no', 'amount', 'currency'])
                ->map(fn (ProjectQuotation $q) => [
                    'id' => $q->id,
                    'vendor_name' => $q->vendor_name,
                    'quotation_no' => $q->quotation_no,
                    'amount' => (float) $q->amount,
                    'currency' => $q->currency,
                ])->values(),
            'vendors' => VendorContact::query()->orderBy('name')->get(['name', 'contact', 'phone', 'email', 'address'])->map(fn (VendorContact $v) => [
                'name' => $v->name,
                'contact' => $v->contact,
                'phone' => $v->phone,
                'email' => $v->email,
                'address' => (string) $v->address,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function orderPayload(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_no' => $po->po_no,
            'po_date' => $po->po_date?->format('Y-m-d'),
            'quotation_id' => $po->quotation_id,
            'vendor_name' => $po->vendor_name,
            'currency' => $po->currency,
            'vendor_address' => (string) $po->vendor_address,
            'attn' => $po->attn,
            'tel' => $po->tel,
            'email' => $po->email,
            'quotation_no' => $po->quotation_no,
            'subject' => $po->subject,
            'tax_percent' => (float) $po->tax_percent,
            'terms' => (string) $po->terms,
            'delivery' => $po->delivery,
            'lead_time' => $po->lead_time,
            'note' => (string) $po->note,
            'ship_name' => $po->ship_name,
            'ship_address' => (string) $po->ship_address,
            'ship_tel' => $po->ship_tel,
            'ship_attn' => $po->ship_attn,
            'use_signature' => $po->use_signature,
            'created_by_name' => $po->created_by_name,
            'items' => $po->items->map(fn ($i) => [
                'description' => $i->description,
                'qty' => (float) $i->qty,
                'unit' => $i->unit,
                'unit_price' => (float) $i->unit_price,
                'due_date' => $i->due_date?->format('Y-m-d'),
            ])->values(),
        ];
    }
}
