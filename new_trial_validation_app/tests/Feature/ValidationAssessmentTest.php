<?php

use App\Actions\Improvements\SaveImprovement;
use App\Actions\Validation\AssignImprovementNumber;
use App\Models\Improvement;
use App\Models\MasterOption;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Tooling;
use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationAssessment;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Models\ValidationPlan;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Trial Validation v2.0 Tahap 2: Validation Assessment register (creates the
 * Case), weekly Validation Plan, Tooling linked to a Case, Improvement numbers,
 * and "create Trial from Case".
 */

function vaUser(string $department, string $role = 'Staff'): User
{
    return User::factory()->create(['role' => $role, 'department' => $department]);
}

function vaProduct(string $name = 'Serum Tube 30ml', string $fg = 'FG-A1'): Product
{
    $product = Product::query()->where('product_name', $name)->first() ?? Product::create(['product_name' => $name, 'finish_good_code' => $fg]);
    $product->is_active = true;
    $product->save();

    return $product;
}

function vaOptions(): void
{
    foreach (['product_type' => 'Tube', 'validation_scope' => 'Retail', 'machine_used' => 'Line A', 'validation_category' => 'Trial NPD base on sales forecast'] as $type => $name) {
        MasterOption::query()->firstOrCreate(['type' => $type, 'name' => $name], ['sort_order' => 0, 'is_active' => true]);
    }
}

function vaPayload(array $overrides = []): array
{
    return array_merge([
        'case_type' => 'NPD',
        'product_id' => vaProduct()->id,
        'receive_date' => '2026-11-10',
        'client' => 'Acme Beauty',
        'sales_month' => '2027-02',
        'qty_po' => 5000,
        'packaging_type' => 'Tube',
        'validation_scope' => ['Retail'],
        'recommended_machine' => 'Line A',
        'qco_no' => 'QCO-77',
        'qco_link' => 'https://example.test/qco/77',
        'pm_received_date' => '2026-11-12',
        'bulk_received_date' => '2026-11-14',
        'risk_level' => 'High',
    ], $overrides);
}

function vaAssessment(?User $by = null, array $overrides = []): ValidationAssessment
{
    vaOptions();
    $by ??= vaUser('PIE');
    test()->actingAs($by)->post(route('validation.assessments.store'), vaPayload($overrides))->assertSessionHasNoErrors();

    return ValidationAssessment::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    MasterOption::query()->delete();
});

test('PIE saves an assessment, which creates the Case with its number', function () {
    $pie = vaUser('PIE');
    vaOptions();

    $this->actingAs($pie)->post(route('validation.assessments.store'), vaPayload())->assertRedirect();

    $assessment = ValidationAssessment::firstOrFail();
    $case = ValidationCase::findOrFail($assessment->case_id);

    expect($case->case_no)->toBe('VAL-NPD-2611-001')
        ->and($case->source)->toBe('assessment')
        ->and($case->product_id)->toBe($assessment->product_id)
        ->and($assessment->risk_level)->toBe('High')
        ->and($assessment->validation_scope)->toBe(['Retail'])
        ->and($assessment->created_by_name)->toBe($pie->name);

    // A second Assessment gets the next number, not a duplicate.
    $this->actingAs($pie)->post(route('validation.assessments.store'), vaPayload(['product_id' => vaProduct('Other', 'FG-B2')->id]))->assertRedirect();
    expect(ValidationCase::orderByDesc('id')->first()->case_no)->toBe('VAL-NPD-2611-002');

    expect(DB::table('activity_logs')->where('module', 'VALIDATION_ASSESSMENT')->where('action', 'CREATE')->count())->toBe(2);
});

test('only PIE or Admin creates; everybody logged in reads', function () {
    vaOptions();

    $this->actingAs(vaUser('PRNI'))->post(route('validation.assessments.store'), vaPayload())->assertForbidden();
    $this->actingAs(vaUser('QA', 'Viewer'))->post(route('validation.assessments.store'), vaPayload())->assertForbidden();
    expect(ValidationAssessment::count())->toBe(0);

    $this->actingAs(vaUser('QA', 'Viewer'))->get(route('validation.assessments.index'))->assertOk();

    $this->actingAs(User::factory()->role('Admin')->create())->post(route('validation.assessments.store'), vaPayload())->assertRedirect();
    expect(ValidationAssessment::count())->toBe(1);
});

test('validation requires a name when the SKU is not in the master, and rejects unknown options', function () {
    vaOptions();
    $pie = vaUser('PIE');

    $this->actingAs($pie)->post(route('validation.assessments.store'), vaPayload(['product_id' => null]))
        ->assertSessionHasErrors('product_name');
    $this->actingAs($pie)->post(route('validation.assessments.store'), vaPayload(['packaging_type' => 'Nope']))
        ->assertSessionHasErrors('packaging_type');
    $this->actingAs($pie)->post(route('validation.assessments.store'), vaPayload(['risk_level' => 'Extreme']))
        ->assertSessionHasErrors('risk_level');
    $this->actingAs($pie)->post(route('validation.assessments.store'), vaPayload(['case_type' => 'XXX']))
        ->assertSessionHasErrors('case_type');
    expect(ValidationCase::count())->toBe(0);

    // A new NPD SKU not in the master is fine with a typed name.
    $this->actingAs($pie)->post(route('validation.assessments.store'), vaPayload(['product_id' => null, 'product_name' => 'Brand New Blush']))
        ->assertSessionHasNoErrors();
    $case = ValidationCase::firstOrFail();
    expect($case->product_id)->toBeNull()->and($case->product_name)->toBe('Brand New Blush');
});

test('editing keeps the case number; N/A clears the date', function () {
    $pie = vaUser('PIE');
    $assessment = vaAssessment($pie);
    $caseNo = $assessment->case->case_no;

    $this->actingAs($pie)->put(route('validation.assessments.update', $assessment), vaPayload([
        'case_type' => 'IMP', // ignored: the type is fixed by the number
        'client' => 'Changed Client',
        'pm_na' => true,
        'pm_received_date' => '2026-11-20',
        'product_id' => $assessment->product_id,
    ]))->assertRedirect();

    $assessment->refresh();
    expect($assessment->client)->toBe('Changed Client')
        ->and($assessment->pm_na)->toBeTrue()
        ->and($assessment->pm_received_date)->toBeNull()
        ->and($assessment->case->case_no)->toBe($caseNo)
        ->and($assessment->case->case_type)->toBe('NPD');
});

test('the register filters by type, risk, client and search', function () {
    $pie = vaUser('PIE');
    vaAssessment($pie, ['client' => 'Alpha Co', 'risk_level' => 'Low', 'product_id' => vaProduct('Alpha Cream', 'FG-1')->id]);
    vaAssessment($pie, ['client' => 'Beta Co', 'risk_level' => 'High', 'case_type' => 'MIX', 'product_id' => vaProduct('Beta Serum', 'FG-2')->id]);

    $count = fn (array $query) => $this->actingAs($pie)->get(route('validation.assessments.index', $query))
        ->viewData('page')['props']['assessments']['total'];

    expect($count([]))->toBe(2)
        ->and($count(['case_type' => 'MIX']))->toBe(1)
        ->and($count(['risk_level' => 'Low']))->toBe(1)
        ->and($count(['client' => 'Beta']))->toBe(1)
        ->and($count(['q' => 'Alpha Cream']))->toBe(1)
        ->and($count(['q' => 'FG-2']))->toBe(1)
        ->and($count(['sales_month' => '2027-02']))->toBe(2)
        ->and($count(['receive_from' => '2026-12-01']))->toBe(0);
});

test('admin deletes an assessment and its case, unless a trial is already linked', function () {
    $admin = User::factory()->role('Admin')->create();
    $pie = vaUser('PIE');
    $assessment = vaAssessment($pie);

    $this->actingAs($pie)->delete(route('validation.assessments.destroy', $assessment))->assertForbidden();

    ValidationCaseLink::create(['case_id' => $assessment->case_id, 'kind' => 'trial', 'ref_id' => 999, 'ref_key' => '999']);
    $this->actingAs($admin)->delete(route('validation.assessments.destroy', $assessment))->assertRedirect();
    expect($assessment->fresh()->deleted_at)->toBeNull();

    ValidationCaseLink::query()->delete();
    $this->actingAs($admin)->delete(route('validation.assessments.destroy', $assessment))->assertRedirect();
    expect($assessment->fresh()->deleted_at)->not->toBeNull()
        ->and(ValidationCase::find($assessment->case_id)->deleted_at)->not->toBeNull();
});

test('PIE requests a weekly plan, PPIC confirms, others cannot', function () {
    $pie = vaUser('PIE');
    $ppic = vaUser('PPIC');
    $other = vaUser('QA');
    $assessment = vaAssessment($pie);

    $this->actingAs($other)->post(route('validation.plans.store'), ['case_id' => $assessment->case_id, 'week' => '2026-12-09'])->assertForbidden();

    // Wednesday 2026-12-09 is normalised to the Monday of its week.
    $this->actingAs($pie)->post(route('validation.plans.store'), ['case_id' => $assessment->case_id, 'week' => '2026-12-09', 'notes' => 'Need line A'])->assertRedirect();
    $plan = ValidationPlan::firstOrFail();
    expect($plan->week_start->toDateString())->toBe('2026-12-07')
        ->and($plan->status)->toBe('Requested')
        ->and($plan->product_name)->toBe('Serum Tube 30ml')
        ->and(Notification::where('type', 'validation_plan')->exists())->toBeTrue();

    $this->actingAs($pie)->post(route('validation.plans.confirm', $plan), [])->assertForbidden();
    $this->actingAs($ppic)->post(route('validation.plans.confirm', $plan), ['ppic_note' => 'Slot Tuesday'])->assertRedirect();

    $plan->refresh();
    expect($plan->status)->toBe('Confirmed')
        ->and($plan->ppic_note)->toBe('Slot Tuesday')
        ->and($plan->confirmed_by_name)->toBe($ppic->name);

    // Everybody reads the list; a confirmed plan cannot be cancelled any more.
    $this->actingAs($other)->get(route('validation.plans.index'))->assertOk();
    $this->actingAs($pie)->post(route('validation.plans.cancel', $plan))->assertStatus(422);
});

test('the requester can cancel an open plan, another PIE cannot', function () {
    $pie = vaUser('PIE');
    $assessment = vaAssessment($pie);
    $this->actingAs($pie)->post(route('validation.plans.store'), ['case_id' => $assessment->case_id, 'week' => '2026-12-09']);
    $plan = ValidationPlan::firstOrFail();

    $this->actingAs(vaUser('PIE'))->post(route('validation.plans.cancel', $plan))->assertForbidden();
    $this->actingAs($pie)->post(route('validation.plans.cancel', $plan))->assertRedirect();
    expect($plan->fresh()->status)->toBe('Cancelled');
});

test('a tooling is linked to a Case without writing pm_toolings, and only once', function () {
    $pie = vaUser('PIE');
    $a = vaAssessment($pie);
    $b = vaAssessment($pie, ['product_id' => vaProduct('Second', 'FG-9')->id]);
    $tooling = Tooling::create(['npd_no' => '1300/NPD', 'product_name' => 'Cushion', 'packaging_type' => 'Cushion', 'status' => 'Menunggu']);
    $before = DB::table('pm_toolings')->where('id', $tooling->id)->first();

    $this->actingAs(vaUser('PRNI'))->post(route('journey.toolings.store', $a->case_id), ['tooling_id' => $tooling->id])->assertForbidden();

    $this->actingAs($pie)->post(route('journey.toolings.store', $a->case_id), ['tooling_id' => $tooling->id])->assertRedirect();
    expect(ValidationCaseLink::where('kind', 'tooling')->where('case_id', $a->case_id)->count())->toBe(1)
        ->and(DB::table('pm_toolings')->where('id', $tooling->id)->first())->toEqual($before);

    // Linking it again to the same Case is a no-op; to another Case it is rejected.
    $this->actingAs($pie)->post(route('journey.toolings.store', $a->case_id), ['tooling_id' => $tooling->id])->assertRedirect();
    expect(ValidationCaseLink::where('kind', 'tooling')->count())->toBe(1);
    $this->actingAs($pie)->post(route('journey.toolings.store', $b->case_id), ['tooling_id' => $tooling->id])->assertSessionHasErrors('tooling_id');

    // The Case page lists it, then unlink frees it.
    $this->actingAs($pie)->get(route('journey.show', $a->case_id))
        ->assertInertia(fn ($page) => $page->has('toolings', 1)->where('assessment.client', 'Acme Beauty')->where('canManageCase', true));

    $this->actingAs($pie)->delete(route('journey.toolings.destroy', [$a->case_id, $tooling->id]))->assertRedirect();
    expect(ValidationCaseLink::where('kind', 'tooling')->count())->toBe(0);
});

test('improvements get their own sequential IMP-YYYY number once', function () {
    $owner = User::factory()->role('Staff')->create();
    $make = fn (string $title, string $at) => tap(Improvement::create(['title' => $title, 'submitted_by_user_id' => $owner->id, 'status' => 'Baru', 'decision' => 'Pending']), function ($i) use ($at) {
        DB::table('pm_improvements')->where('id', $i->id)->update(['submitted_at' => $at]);
    })->fresh();

    $first = $make('One', '2026-03-01 10:00:00');
    $second = $make('Two', '2026-04-01 10:00:00');
    $nextYear = $make('Three', '2027-01-05 10:00:00');

    $assign = app(AssignImprovementNumber::class);
    expect($assign($first))->toBe('IMP-2026-001')
        ->and($assign($second))->toBe('IMP-2026-002')
        ->and($assign($first))->toBe('IMP-2026-001')
        ->and($assign($nextYear))->toBe('IMP-2027-001');

    expect(AssignImprovementNumber::numbersFor([$first->id, $second->id]))->toBe([$first->id => 'IMP-2026-001', $second->id => 'IMP-2026-002']);
});

test('submitting an improvement numbers it, and the backfill command is idempotent with a dry run', function () {
    $owner = User::factory()->role('Staff')->create();

    $created = app(SaveImprovement::class)(null, ['title' => 'Fresh idea'], $owner);
    $year = Carbon::now()->format('Y');
    expect(AssignImprovementNumber::numbersFor([$created->id])[$created->id])->toBe("IMP-{$year}-001");

    $old = Improvement::create(['title' => 'Old one', 'submitted_by_user_id' => $owner->id, 'status' => 'Baru', 'decision' => 'Pending']);

    $this->artisan('validation:number-improvements', ['--dry-run' => true])->assertSuccessful();
    expect(AssignImprovementNumber::numbersFor([$old->id]))->toBe([]);

    $this->artisan('validation:number-improvements')->assertSuccessful();
    $numbers = AssignImprovementNumber::numbersFor([$created->id, $old->id]);
    expect($numbers)->toHaveCount(2)->and($numbers[$created->id])->toBe("IMP-{$year}-001");

    $this->artisan('validation:number-improvements')->assertSuccessful();
    expect(AssignImprovementNumber::numbersFor([$created->id, $old->id]))->toBe($numbers);
});

test('creating a trial from a Case pre-fills from the assessment and joins that Case', function () {
    $pie = vaUser('PIE');
    $assessment = vaAssessment($pie);
    $case = $assessment->case;

    $this->actingAs($pie)->get(route('trials.create', ['case' => $case->id]))
        ->assertInertia(fn ($page) => $page
            ->where('caseRef.case_no', $case->case_no)
            ->where('prefill.product_id', $assessment->product_id)
            ->where('prefill.product_type', 'Tube')
            ->where('prefill.risk_level', 'High')
            ->where('prefill.validation_scope', ['Retail'])
            ->where('prefill.machine_used', ['Line A'])
            ->where('prefill.validation_category', 'Trial NPD base on sales forecast')
            ->where('prefill.pm_ready_date', '2026-11-12'));

    // An unknown Case sends the user back with an error toast, not a broken form.
    $this->actingAs($pie)->get(route('trials.create', ['case' => 99999]))->assertRedirect();

    $this->actingAs($pie)->post(route('trials.store'), [
        'case_id' => $case->id,
        'product_id' => $assessment->product_id,
        'product_type' => 'Tube',
        'validation_date' => '2026-12-01',
        'validation_category' => 'Trial NPD base on sales forecast',
        'risk_level' => 'High',
        'validation_scope' => ['Retail'],
        'machine_used' => ['Line A'],
        'estimate_qty' => 100,
        'batch_number' => 'B-1',
        'bulk_code' => 'BULK-1',
        'support_team' => 'PIE',
        'initiated_person_team' => 'Zahra',
        'reason' => 'New',
        'bom' => 'BOM',
    ])->assertRedirect();

    $trial = Trial::firstOrFail();
    $links = ValidationCaseLink::where('kind', 'trial')->where('ref_key', (string) $trial->id)->get();
    expect($links)->toHaveCount(1)
        ->and((int) $links->first()->case_id)->toBe($case->id)
        // Still a Draft, so not counted as an attempt yet.
        ->and($links->first()->attempt_no)->toBeNull();

    // No second Case was made behind the user's back.
    expect(ValidationCase::count())->toBe(1);
});

test('a trial created for a Case of another type still joins the chosen Case', function () {
    $pie = vaUser('PIE');
    $assessment = vaAssessment($pie, ['case_type' => 'IMP']);

    // The trial's own category says NPD, so the automatic grouping would open an NPD Case first.
    $this->actingAs($pie)->post(route('trials.store'), [
        'case_id' => $assessment->case_id,
        'product_id' => $assessment->product_id,
        'product_type' => 'Tube',
        'validation_date' => '2026-12-01',
        'validation_category' => 'Trial NPD base on sales forecast',
        'risk_level' => 'Low',
        'validation_scope' => ['Retail'],
        'machine_used' => ['Line A'],
        'estimate_qty' => 1,
        'batch_number' => 'B',
        'bulk_code' => 'C',
        'support_team' => 'PIE',
        'initiated_person_team' => 'Z',
        'reason' => 'r',
        'bom' => 'b',
    ])->assertRedirect();

    $trial = Trial::firstOrFail();
    $link = ValidationCaseLink::where('kind', 'trial')->where('ref_key', (string) $trial->id)->firstOrFail();
    expect((int) $link->case_id)->toBe($assessment->case_id);
});

test('assessment lang group has id en ko parity', function () {
    $keys = fn (string $locale) => array_keys(Arr::dot(require lang_path("{$locale}/assessment.php")));

    expect($keys('en'))->toBe($keys('id'))->and($keys('ko'))->toBe($keys('id'));
});
