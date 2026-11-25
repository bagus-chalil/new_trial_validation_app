<?php

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectSetting;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\VendorContact;
use App\Services\Projects\PurchaseOrderNumbering;
use App\Support\PurchaseOrderSettings;
use Illuminate\Support\Carbon;

function poPie(): User
{
    return User::factory()->create(['role' => 'Staff', 'department' => 'PIE']);
}

function poPayload(array $overrides = []): array
{
    return array_merge([
        'po_no' => '001/PO/PI/XI/2026',
        'po_date' => '2026-11-18',
        'vendor_name' => 'PT Keosan',
        'currency' => 'idr',
        'vendor_address' => "Jl. Jababeka\nCikarang",
        'attn' => 'Mr. Won',
        'tel' => '021-111',
        'email' => 'won@example.test',
        'quotation_no' => 'Q-1',
        'subject' => 'Tooling set',
        'tax_percent' => 11,
        'terms' => '100% after delivery',
        'delivery' => 'PT Cosmax',
        'lead_time' => '2 weeks',
        'use_signature' => '0',
        'items' => [
            ['description' => 'Nozzle', 'qty' => 2, 'unit' => 'Unit', 'unit_price' => 1000000, 'due_date' => '2026-12-01'],
            ['description' => 'Cap', 'qty' => 1, 'unit' => 'pcs', 'unit_price' => 500000],
        ],
    ], $overrides);
}

function poProject(array $overrides = []): Project
{
    return Project::create(array_merge(['name' => 'Alpha', 'code' => 'PRJ-001', 'status' => 'On Progress', 'pic_name' => 'Zahra'], $overrides));
}

test('a PIE account creates a PO: items, totals, vendor book, project po number and the PO stage', function () {
    $user = poPie();
    $project = poProject();
    $stage = $project->stages()->create(['name' => 'PR & PO', 'status' => 'On Progress', 'plan_start' => '2026-11-01', 'plan_end' => '2026-11-30', 'progress' => 20, 'sort_order' => 0]);
    $other = $project->stages()->create(['name' => 'Fabrication', 'status' => 'Not Started', 'progress' => 0, 'sort_order' => 1]);

    $response = $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload());

    $order = PurchaseOrder::firstOrFail();
    $response->assertRedirect(route('projects.purchase-orders.show', $order));

    expect($order->po_no)->toBe('001/PO/PI/XI/2026')
        ->and($order->currency)->toBe('IDR')
        ->and($order->items)->toHaveCount(2)
        ->and($order->totals())->toBe(['sub' => 2500000.0, 'tax' => 275000.0, 'total' => 2775000.0])
        ->and($order->created_by_user_id)->toBe($user->id);

    expect($project->fresh()->po)->toBe('001/PO/PI/XI/2026')
        ->and($project->fresh()->vendor)->toBe('PT Keosan');
    expect(VendorContact::where('name', 'PT Keosan')->value('contact'))->toBe('Mr. Won');

    $stage->refresh();
    expect($stage->status)->toBe('Completed')
        ->and($stage->progress)->toBe(100)
        ->and($stage->actual_end->toDateString())->toBe('2026-11-18')
        ->and($other->fresh()->status)->toBe('Not Started');

    expect(ActivityLog::where('module', 'PROJECT')->where('action', 'CREATE_PO')->count())->toBe(1);
});

test('numbers must be unique and the next number follows the pattern and restarts each month', function () {
    $project = poProject();
    $user = poPie();
    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload())->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload())
        ->assertSessionHasErrors('po_no');
    expect(PurchaseOrder::count())->toBe(1);

    $numbering = app(PurchaseOrderNumbering::class);
    expect($numbering->next(Carbon::parse('2026-11-20')))->toBe('002/PO/PI/XI/2026')
        ->and($numbering->next(Carbon::parse('2026-12-02')))->toBe('001/PO/PI/XII/2026')
        ->and($numbering->next(Carbon::parse('2026-11-20'), '{NNN}/PO/{YY}'))->toBe('001/PO/26');

    // the create page proposes that number
    $this->actingAs($user)->get(route('projects.purchase-orders.create', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('projects/purchase-orders/form')->where('order.id', null));
});

test('Finance is Admin or a PIE/PI account only; viewers and other departments get nothing', function () {
    $project = poProject();
    $order = PurchaseOrder::create(['project_id' => $project->id, 'po_no' => 'X-1', 'po_date' => '2026-11-18', 'vendor_name' => 'V']);

    $viewer = User::factory()->create(['role' => 'Viewer', 'department' => 'PIE']);
    $prod = User::factory()->create(['role' => 'Staff', 'department' => 'PROD']);

    foreach ([$viewer, $prod] as $user) {
        $this->actingAs($user)->get(route('projects.purchase-orders.create', $project))->assertForbidden();
        $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload())->assertForbidden();
        $this->actingAs($user)->get(route('projects.purchase-orders.edit', $order))->assertForbidden();
        $this->actingAs($user)->delete(route('projects.purchase-orders.destroy', $order))->assertForbidden();
        $this->actingAs($user)->get(route('projects.purchase-orders.show', $order))->assertForbidden();
        $this->actingAs($user)->get(route('projects.purchase-orders.pdf', $order))->assertForbidden();
        $this->actingAs($user)->get(route('projects.purchase-orders.index'))->assertForbidden();
    }

    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin)->get(route('projects.purchase-orders.create', $project))->assertOk();
    $this->actingAs(poPie())->get(route('projects.purchase-orders.create', $project))->assertOk();
});

test('updating replaces the items and deleting falls the project back to its newest remaining PO', function () {
    $user = poPie();
    $project = poProject();
    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload())->assertSessionHasNoErrors();
    $first = PurchaseOrder::firstOrFail();
    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload(['po_no' => '002/PO/PI/XI/2026']))->assertSessionHasNoErrors();
    $second = PurchaseOrder::where('po_no', '002/PO/PI/XI/2026')->firstOrFail();

    $this->actingAs($user)->put(route('projects.purchase-orders.update', $first), poPayload([
        'items' => [['description' => 'Only one', 'qty' => 3, 'unit' => 'Set', 'unit_price' => 100]],
    ]))->assertRedirect(route('projects.purchase-orders.show', $first));

    expect($first->fresh()->items)->toHaveCount(1)
        ->and($first->fresh()->totals()['sub'])->toBe(300.0);

    expect($project->fresh()->po)->toBe('001/PO/PI/XI/2026');

    $this->actingAs($user)->delete(route('projects.purchase-orders.destroy', $first))->assertRedirect(route('projects.show', $project));
    expect(PurchaseOrder::find($first->id))->toBeNull()
        ->and($project->fresh()->po)->toBe('002/PO/PI/XI/2026');
});

test('validation rejects missing items, a negative price and a foreign quotation', function () {
    $user = poPie();
    $project = poProject();
    $otherProject = poProject(['name' => 'Beta', 'code' => 'PRJ-002']);
    $foreign = ProjectQuotation::create(['project_id' => $otherProject->id, 'vendor_name' => 'V', 'currency' => 'IDR', 'status' => 'Diterima', 'quotation_no' => '', 'link' => '']);

    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload(['items' => []]))->assertSessionHasErrors('items');
    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload(['items' => [['description' => 'x', 'qty' => 1, 'unit_price' => -5]]]))->assertSessionHasErrors('items.0.unit_price');
    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload(['quotation_id' => $foreign->id]))->assertSessionHasErrors('quotation_id');
    expect(PurchaseOrder::count())->toBe(0);
});

test('the PDF is rendered from the template with totals and honours the signature switch', function () {
    $project = poProject();
    $user = poPie();
    $png = 'data:image/png;base64,'.base64_encode('sig');
    PurchaseOrderSettings::save(['lang' => 'id', 'signers' => [['label' => 'Disetujui', 'name' => 'Budi', 'title' => 'Manager', 'image' => $png]]]);

    $this->actingAs($user)->post(route('projects.purchase-orders.store', $project), poPayload(['use_signature' => '1']))->assertSessionHasNoErrors();
    $order = PurchaseOrder::firstOrFail();

    $response = $this->actingAs($user)->get(route('projects.purchase-orders.pdf', $order))->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    $html = $response->getContent();
    expect($html)->toContain('001/PO/PI/XI/2026')
        ->toContain('PT Keosan')
        ->toContain('KEPADA')                  // Indonesian labels from the template language
        ->toContain('2.775.000')               // grand total with Indonesian separators
        ->toContain($png);                     // signature image printed

    $order->update(['use_signature' => false]);
    $plain = $this->actingAs($user)->get(route('projects.purchase-orders.pdf', $order))->getContent();
    expect($plain)->not->toContain($png)->toContain('Budi');
});

test('only an Admin edits the template; it validates the pattern and drops unsafe images', function () {
    $this->actingAs(poPie())->get(route('projects.purchase-orders.template'))->assertForbidden();
    $this->actingAs(poPie())->put(route('projects.purchase-orders.template.update'), ['company' => 'X'])->assertForbidden();

    $admin = User::factory()->create(['role' => 'Admin']);
    $base = ['company' => 'PT Test', 'title' => 'PO', 'pattern' => '{NNN}/PO/{YYYY}', 'date_format' => 'dd/mm/yyyy', 'lang' => 'en', 'tax' => 10];

    $this->actingAs($admin)->put(route('projects.purchase-orders.template.update'), array_merge($base, ['pattern' => 'PO-NO-SEQUENCE']))
        ->assertSessionHasErrors('pattern');

    $this->actingAs($admin)->put(route('projects.purchase-orders.template.update'), $base + [
        'logo' => 'javascript:alert(1)',
        'signers' => [
            ['label' => 'A', 'name' => 'Ann', 'title' => 'T', 'image' => 'data:image/png;base64,'.base64_encode('ok')],
            ['label' => 'B', 'image' => 'data:text/html;base64,PHNjcmlwdD4='],
        ],
    ])->assertRedirect(route('projects.purchase-orders.template'));

    $stored = PurchaseOrderSettings::get();
    expect($stored['company'])->toBe('PT Test')
        ->and($stored['pattern'])->toBe('{NNN}/PO/{YYYY}')
        ->and($stored['logo'])->toBe('')
        ->and($stored['signers'][0]['image'])->toStartWith('data:image/png')
        ->and($stored['signers'][1]['image'])->toBe('')
        ->and($stored['signers'])->toHaveCount(3)
        ->and(ProjectSetting::count())->toBe(1);

    $this->actingAs($admin)->get(route('projects.purchase-orders.template'))->assertOk()->assertInertia(fn ($page) => $page->component('projects/purchase-orders/template')->where('template.company', 'PT Test'));
});

test('the cross-project list filters by vendor and search and the project page lists its POs', function () {
    $user = poPie();
    $a = poProject();
    $b = poProject(['name' => 'Beta', 'code' => 'PRJ-002']);
    PurchaseOrder::create(['project_id' => $a->id, 'po_no' => '001/A', 'po_date' => '2026-11-01', 'vendor_name' => 'Alpha Vendor', 'subject' => 'Machine']);
    PurchaseOrder::create(['project_id' => $b->id, 'po_no' => '002/B', 'po_date' => '2026-11-02', 'vendor_name' => 'Beta Vendor', 'subject' => 'Tooling']);

    $this->actingAs($user)->get(route('projects.purchase-orders.index'))->assertInertia(fn ($page) => $page->has('items.data', 2));
    $this->actingAs($user)->get(route('projects.purchase-orders.index', ['vendor' => 'Beta Vendor']))->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.po_no', '002/B'));
    $this->actingAs($user)->get(route('projects.purchase-orders.index', ['q' => 'machine']))->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.po_no', '001/A'));
    $this->actingAs($user)->get(route('projects.show', $a))->assertInertia(fn ($page) => $page->has('purchaseOrders', 1)->where('purchaseOrders.0.po_no', '001/A')->where('can.purchaseOrder', true));
});
