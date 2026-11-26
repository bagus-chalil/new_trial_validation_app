<?php

use App\Actions\Validation\IssueImNumber;
use App\Actions\Validation\IssueQcoNumber;
use App\Models\ActivityLog;
use App\Models\ImBrandCode;
use App\Models\ImNumber;
use App\Models\Product;
use App\Models\QcoNumber;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Services\Validation\IpcBatchLookup;
use App\Support\ImNumberFormat;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Trial Validation v2.0 Tahap 3: QCO (PRNI) and IM (QA) number registers, the
 * Case links, and the read-only IPC batch lookup.
 */

function vnUser(string $department, string $role = 'Staff'): User
{
    return User::factory()->create(['role' => $role, 'department' => $department]);
}

function vnCase(?Product $product = null): ValidationCase
{
    static $seq = 0;
    $seq++;

    return ValidationCase::create([
        'case_no' => sprintf('VAL-NPD-2611-%03d', $seq),
        'case_type' => 'NPD',
        'period' => '2611',
        'seq' => $seq,
        'product_id' => $product?->id,
        'product_name' => $product?->product_name ?? 'Serum',
        'source' => 'manual',
    ]);
}

function vnProduct(string $fg = 'FG-100'): Product
{
    $product = Product::create(['product_name' => 'Calming Cleanser '.$fg, 'finish_good_code' => $fg]);
    $product->is_active = true;
    $product->save();

    return $product;
}

function vnBrand(string $code = 'BHM'): ImBrandCode
{
    return ImBrandCode::firstOrCreate(['code' => $code], ['name' => 'Bhumi', 'is_active' => true]);
}

function vnImPayload(array $overrides = []): array
{
    return array_merge([
        'brand_code' => 'BHM',
        'product_part' => 'bhumi  calming cleanser 100ml',
        'fg_codes' => ['631208004', '631208004101'],
        'line_machine' => 'LINE SKINCARE - MACHINE FILLING TUBE (1 NOZZLE)',
        'customer' => 'PT. Riza Kreasi Naturalindo',
    ], $overrides);
}

test('IM numbers are normalised: spaces, case and the V suffix', function () {
    expect(ImNumberFormat::normalize('IM-BHM- BHUMI CALMING CLEANSER 100ML- V2'))->toBe('IM-BHM-BHUMI CALMING CLEANSER 100ML V2')
        ->and(ImNumberFormat::normalize(' im-abl- sculpt   calm lily deodorant 25ml v2 '))->toBe('IM-ABL-SCULPT CALM LILY DEODORANT 25ML V2')
        ->and(ImNumberFormat::normalize(''))->toBe('')
        ->and(ImNumberFormat::compose('bhm', 'Bhumi  Calming', 1))->toBe('IM-BHM-BHUMI CALMING')
        ->and(ImNumberFormat::compose('BHM', 'BHUMI CALMING', 3))->toBe('IM-BHM-BHUMI CALMING V3');
});

test('PRNI issues sequential QCO numbers, nobody else can', function () {
    $prni = vnUser('PRNI');

    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme', 'product_name' => 'Serum A', 'qty_po' => 5000])->assertRedirect();
    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme', 'product_name' => 'Serum B'])->assertRedirect();

    $year = now()->format('Y');
    expect(QcoNumber::orderBy('id')->pluck('number')->all())->toBe(["QCO-{$year}-0001", "QCO-{$year}-0002"])
        ->and(ActivityLog::where('module', 'QCO_NUMBER')->where('action', 'ISSUE_QCO')->count())->toBe(2);

    $this->actingAs(vnUser('PIE'))->post(route('validation.qco.store'), ['client' => 'X', 'product_name' => 'Y'])->assertForbidden();
    $this->actingAs(vnUser('QA'))->post(route('validation.qco.store'), ['client' => 'X', 'product_name' => 'Y'])->assertForbidden();
    expect(QcoNumber::count())->toBe(2);
});

test('a QCO needs a client and a SKU, and a void number is kept and never reused', function () {
    $prni = vnUser('PRNI');

    $this->actingAs($prni)->post(route('validation.qco.store'), ['product_name' => 'Y'])->assertSessionHasErrors('client');
    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme'])->assertSessionHasErrors('product_name');
    expect(QcoNumber::count())->toBe(0);

    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme', 'product_name' => 'A']);
    $first = QcoNumber::firstOrFail();

    $this->actingAs($prni)->post(route('validation.qco.void', $first), [])->assertSessionHasErrors('reason');
    $this->actingAs($prni)->post(route('validation.qco.void', $first), ['reason' => 'salah client'])->assertRedirect();
    expect($first->fresh()->status)->toBe('Void');

    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme', 'product_name' => 'B']);
    expect(QcoNumber::orderBy('id')->pluck('seq')->all())->toBe([1, 2]);
});

test('everybody logged in reads the registers, with a search', function () {
    $prni = vnUser('PRNI');
    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme Beauty', 'product_name' => 'Serum']);
    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Other Co', 'product_name' => 'Cream']);

    $this->actingAs(vnUser('PRD'))->get(route('validation.qco.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('validation/qco/index')->where('numbers.total', 2)->where('can.issue', false));

    $this->actingAs(vnUser('PRD'))->get(route('validation.qco.index', ['q' => 'Acme']))
        ->assertInertia(fn ($page) => $page->where('numbers.total', 1));

    $this->actingAs(vnUser('PRD'))->get(route('validation.im.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('validation/im/index')->where('can.issue', false));
});

test('QA issues an IM with a normalised number, and rejects a duplicate or an unknown brand', function () {
    vnBrand('BHM');
    $qa = vnUser('QA');

    $this->actingAs($qa)->post(route('validation.im.store'), vnImPayload())->assertRedirect();

    $im = ImNumber::firstOrFail();
    expect($im->number)->toBe('IM-BHM-BHUMI CALMING CLEANSER 100ML')
        ->and($im->version_no)->toBe(1)
        ->and($im->status)->toBe('Active')
        ->and($im->fg_codes)->toBe(['631208004', '631208004101'])
        ->and(ActivityLog::where('module', 'IM_NUMBER')->where('action', 'ISSUE_IM')->count())->toBe(1);

    $this->actingAs($qa)->post(route('validation.im.store'), vnImPayload(['product_part' => 'Bhumi Calming Cleanser 100ML']))->assertSessionHasErrors('product_part');
    $this->actingAs($qa)->post(route('validation.im.store'), vnImPayload(['brand_code' => 'ZZZ']))->assertSessionHasErrors('brand_code');
    expect(ImNumber::count())->toBe(1);

    $this->actingAs(vnUser('PRNI'))->post(route('validation.im.store'), vnImPayload(['product_part' => 'Other']))->assertForbidden();
});

test('QAC also counts as QA, and a new brand code is added by QA only', function () {
    $this->actingAs(vnUser('QAC'))->post(route('validation.im.brands.store'), ['code' => 'abl', 'name' => 'Able'])->assertRedirect();
    expect(ImBrandCode::where('code', 'ABL')->exists())->toBeTrue();

    $this->actingAs(vnUser('QAC'))->post(route('validation.im.brands.store'), ['code' => 'ABL', 'name' => 'Dup'])->assertSessionHasErrors('code');
    $this->actingAs(vnUser('PIE'))->post(route('validation.im.brands.store'), ['code' => 'ZZ', 'name' => 'No'])->assertForbidden();
});

test('revising an IM makes V2, replaces the old one, and only an Active IM can be revised', function () {
    vnBrand('BHM');
    $qa = vnUser('QA');
    $first = app(IssueImNumber::class)->issue(vnImPayload(), $qa);

    $this->actingAs($qa)->post(route('validation.im.revise', $first), ['revision_reason' => 'C. Address on UB & Primary'])->assertRedirect();

    $second = ImNumber::where('version_no', 2)->firstOrFail();
    expect($second->number)->toBe('IM-BHM-BHUMI CALMING CLEANSER 100ML V2')
        ->and($second->replaces_id)->toBe($first->id)
        ->and($second->replaces_number)->toBe($first->number)
        ->and($second->revision_reason)->toBe('C. Address on UB & Primary')
        ->and($second->fg_codes)->toBe(['631208004', '631208004101'])
        ->and($first->fresh()->status)->toBe('Superseded');

    // The superseded one cannot be revised again; the new one can (V3).
    $this->actingAs($qa)->post(route('validation.im.revise', $first), ['revision_reason' => 'again'])->assertSessionHasErrors('revision_reason');
    $this->actingAs($qa)->post(route('validation.im.revise', $second), ['revision_reason' => 'next'])->assertRedirect();
    expect(ImNumber::where('number', 'IM-BHM-BHUMI CALMING CLEANSER 100ML V3')->exists())->toBeTrue();

    $this->actingAs($qa)->post(route('validation.im.revise', $second), [])->assertSessionHasErrors('revision_reason');
});

test('a void IM stays on record and its number is never reused', function () {
    vnBrand('BHM');
    $qa = vnUser('QA');
    $im = app(IssueImNumber::class)->issue(vnImPayload(), $qa);

    $this->actingAs($qa)->post(route('validation.im.void', $im), ['reason' => 'typo'])->assertRedirect();
    expect($im->fresh()->status)->toBe('Void');

    $this->actingAs($qa)->post(route('validation.im.store'), vnImPayload())->assertSessionHasErrors('product_part');
    $this->actingAs($qa)->post(route('validation.im.revise', $im), ['revision_reason' => 'x'])->assertSessionHasErrors('revision_reason');
});

test('QCO and IM numbers are recorded on a Case once, by the right role', function () {
    vnBrand('BHM');
    $prni = vnUser('PRNI');
    $qa = vnUser('QA');
    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme', 'product_name' => 'Serum']);
    $qco = QcoNumber::firstOrFail();
    $im = app(IssueImNumber::class)->issue(vnImPayload(), $qa);
    $case = vnCase();
    $other = vnCase();

    $this->actingAs($prni)->post(route('journey.numbers.store', $case), ['kind' => 'qco', 'number_id' => $qco->id])->assertRedirect();
    $this->actingAs($qa)->post(route('journey.numbers.store', $case), ['kind' => 'im', 'number_id' => $im->id])->assertRedirect();

    expect(ValidationCaseLink::where('case_id', $case->id)->where('kind', 'qco')->value('ref_key'))->toBe($qco->number)
        ->and(ValidationCaseLink::where('case_id', $case->id)->where('kind', 'im')->value('ref_key'))->toBe($im->number);

    // A number belongs to one Case only; the wrong role is refused.
    $this->actingAs($prni)->post(route('journey.numbers.store', $other), ['kind' => 'qco', 'number_id' => $qco->id])->assertSessionHasErrors('number');
    $this->actingAs($qa)->post(route('journey.numbers.store', $other), ['kind' => 'qco', 'number_id' => $qco->id])->assertForbidden();
    $this->actingAs(vnUser('PRD'))->post(route('journey.numbers.store', $other), ['kind' => 'im', 'number_id' => $im->id])->assertForbidden();

    // The Case page shows both, and the register shows the Case number.
    $this->actingAs($prni)->get(route('journey.show', $case))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('numbers.qco.0.number', $qco->number)
            ->where('numbers.im.0.number', $im->number)
            ->where('canLinkNumbers.qco', true)
            ->where('canLinkNumbers.im', false));
    $this->actingAs($prni)->get(route('validation.qco.index'))
        ->assertInertia(fn ($page) => $page->where('numbers.data.0.case_no', $case->case_no));

    $this->actingAs($prni)->delete(route('journey.numbers.destroy', [$case, 'qco', $qco->id]))->assertRedirect();
    expect(ValidationCaseLink::where('kind', 'qco')->count())->toBe(0);
});

test('a void number cannot be linked, and the Assessment manual QCO field still works', function () {
    $prni = vnUser('PRNI');
    $this->actingAs($prni)->post(route('validation.qco.store'), ['client' => 'Acme', 'product_name' => 'Serum']);
    $qco = QcoNumber::firstOrFail();
    $this->actingAs($prni)->post(route('validation.qco.void', $qco), ['reason' => 'x']);

    $this->actingAs($prni)->post(route('journey.numbers.store', vnCase()), ['kind' => 'qco', 'number_id' => $qco->id])->assertNotFound();

    expect(Schema::hasColumn('vd_assessments', 'qco_no'))->toBeTrue();
});

test('numbers lang group has id en ko parity', function () {
    $keys = fn (string $locale) => array_keys(Arr::dot(require lang_path("{$locale}/numbers.php")));

    expect($keys('en'))->toBe($keys('id'))->and($keys('ko'))->toBe($keys('id'));
});

// ---- IPC read-only lookup -------------------------------------------------

function vnIpcDatabase(): void
{
    config(['database.connections.ipc_system' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
    DB::purge('ipc_system');

    $schema = Schema::connection('ipc_system');
    $schema->create('master_products', function ($t) {
        $t->id();
        $t->string('fg_code');
        $t->string('product_name');
    });
    $schema->create('ipc_batches', function ($t) {
        $t->id();
        $t->unsignedBigInteger('master_product_id');
        $t->string('no_batch');
        $t->string('current_stage')->default('startup');
        $t->string('flow_type')->default('full');
        $t->date('mixing_date')->nullable();
        $t->date('exp_date')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    $schema->create('startup_checks', function ($t) {
        $t->id();
        $t->unsignedBigInteger('ipc_batch_id');
        $t->string('im_document_number')->nullable();
    });
}

function vnIpcBatch(string $fg, string $noBatch, ?string $im = null, bool $deleted = false): void
{
    $conn = DB::connection('ipc_system');
    $product = $conn->table('master_products')->where('fg_code', $fg)->first()
        ?? (object) ['id' => $conn->table('master_products')->insertGetId(['fg_code' => $fg, 'product_name' => 'IPC '.$fg])];

    $id = $conn->table('ipc_batches')->insertGetId([
        'master_product_id' => $product->id,
        'no_batch' => $noBatch,
        'current_stage' => 'filling',
        'created_at' => '2026-11-20 08:00:00',
        'deleted_at' => $deleted ? '2026-11-21 08:00:00' : null,
    ]);

    if ($im !== null) {
        $conn->table('startup_checks')->insert(['ipc_batch_id' => $id, 'im_document_number' => $im]);
    }
}

test('the Journey lists IPC batches matched by FG code or by IM number, read-only', function () {
    vnIpcDatabase();
    vnBrand('BHM');
    $product = vnProduct('FG-100');
    $case = vnCase($product);
    $im = app(IssueImNumber::class)->issue(vnImPayload(['fg_codes' => ['FG-IM-ONLY']]), vnUser('QA'));
    ValidationCaseLink::create(['case_id' => $case->id, 'kind' => 'im', 'ref_id' => $im->id, 'ref_key' => $im->number]);

    vnIpcBatch('FG-100', 'B-FG-1');
    vnIpcBatch('FG-OTHER', 'B-IM-1', 'IM-BHM- BHUMI CALMING CLEANSER 100ML');   // loose spacing still matches
    vnIpcBatch('FG-OTHER', 'B-NOPE', 'IM-BHM-SOMETHING ELSE');
    vnIpcBatch('FG-IM-ONLY', 'B-FG-2');                                          // FG code stored on the IM
    vnIpcBatch('FG-100', 'B-DELETED', null, true);
    vnIpcBatch('FG-ZZZ', 'B-UNRELATED');

    $result = app(IpcBatchLookup::class)->forCase($case);

    expect($result['available'])->toBeTrue()
        ->and(collect($result['batches'])->pluck('no_batch')->sort()->values()->all())->toBe(['B-FG-1', 'B-FG-2', 'B-IM-1'])
        ->and(collect($result['batches'])->firstWhere('no_batch', 'B-IM-1')['matched_by'])->toBe('im')
        ->and(collect($result['batches'])->firstWhere('no_batch', 'B-FG-1')['matched_by'])->toBe('fg');

    $this->actingAs(vnUser('PRD'))->get(route('journey.show', $case))
        ->assertInertia(fn ($page) => $page->where('ipc.available', true)->has('ipc.batches', 3));

    // Nothing was written to the IPC database.
    expect(DB::connection('ipc_system')->table('ipc_batches')->count())->toBe(6);
});

test('an N/A FG code never matches IPC batches, and an empty Case has no batches', function () {
    vnIpcDatabase();
    vnIpcBatch('N/A', 'B-NA');
    $case = vnCase(vnProduct('N/A'));

    expect(app(IpcBatchLookup::class)->forCase($case))->toBe(['available' => true, 'batches' => []]);
});

test('an unreachable IPC database leaves the Journey working with "not available"', function () {
    config(['database.connections.ipc_system' => ['driver' => 'sqlite', 'database' => '/nonexistent/dir/ipc.sqlite', 'prefix' => '']]);
    DB::purge('ipc_system');

    $case = vnCase(vnProduct('FG-100'));

    expect(app(IpcBatchLookup::class)->forCase($case))->toBe(['available' => false, 'batches' => []]);
    $this->actingAs(vnUser('PRD'))->get(route('journey.show', $case))->assertOk()
        ->assertInertia(fn ($page) => $page->where('ipc.available', false));
});

test('issuing QCO numbers concurrently-safe path uses a locked counter row', function () {
    $prni = vnUser('PRNI');
    app(IssueQcoNumber::class)->issue(['client' => 'A', 'product_name' => 'A'], $prni);
    app(IssueQcoNumber::class)->issue(['client' => 'B', 'product_name' => 'B'], $prni);

    expect(DB::table('vd_number_counters')->where('scope', 'qco')->where('period', now()->format('Y'))->value('last_seq'))->toBe(2);
    Carbon::setTestNow();
});
