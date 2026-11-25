<?php

use App\Models\MasterOption;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Models\ValidationTrialMeta;
use App\Services\Validation\CaseJourney;
use App\Services\Validation\CaseNumbering;
use App\Services\Validation\TrialOutcome;
use App\Support\ValidationCaseType;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Trial Validation v2.0 Tahap 1: Case foundation, backfill, Product Journey.
 */

function vdProduct(string $name = 'Cream Tube 50ml', string $fg = 'FG-1'): Product
{
    $product = Product::create(['product_name' => $name, 'finish_good_code' => $fg]);
    $product->is_active = true;
    $product->save();

    return $product;
}

function vdTrial(Product $product, array $attributes = []): Trial
{
    $trial = Trial::create(array_merge([
        'trial_code' => 'TRIAL-VD-'.uniqid(),
        'product_id' => $product->id,
        'product_name' => $product->product_name,
        'finish_good_code' => $product->finish_good_code,
        'product_type' => 'Tube',
        'validation_category' => 'Trial NPD base on sales forecast',
        'progress_status' => 'Approved',
        'current_step' => 'Review',
        'created_by' => 'owner@local.test',
        'validation_date' => '2026-11-10',
        'batch_number' => 'B1',
        'bulk_code' => 'BC1',
        'support_team' => 'QA',
        'initiated_person_team' => 'Someone',
        'reason' => 'Testing',
        'bom' => 'BOM',
        'revision_no' => 0,
    ], Arr::except($attributes, ['created_at'])));

    // created_at is not mass-assignable on the shared model.
    if (isset($attributes['created_at'])) {
        DB::table('trials_header')->where('id', $trial->id)->update(['created_at' => $attributes['created_at']]);
        $trial->refresh();
    }

    return $trial;
}

function vdNotOk(Trial $trial): void
{
    DB::table('trials_results')->insert([
        'trial_id' => $trial->id,
        'parameter_id' => 1,
        'decision' => 'NOT OK',
        'result_value' => 'x',
        'remark' => 'y',
    ]);
}

test('the case number generator counts per type and period and formats the number', function () {
    $numbering = app(CaseNumbering::class);

    expect($numbering->next('NPD', '2611')['case_no'])->toBe('VAL-NPD-2611-001');
    expect($numbering->next('NPD', '2611')['case_no'])->toBe('VAL-NPD-2611-002');
    expect($numbering->next('IMP', '2611')['case_no'])->toBe('VAL-IMP-2611-001');
    expect($numbering->next('NPD', '2612')['case_no'])->toBe('VAL-NPD-2612-001');
    expect(DB::table('vd_case_counters')->where('case_type', 'NPD')->where('period', '2611')->value('last_seq'))->toBe(2);

    expect(fn () => $numbering->next('XXX', '2611'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $numbering->next('NPD', '26-11'))->toThrow(InvalidArgumentException::class);
});

test('case type follows the agreed mapping', function (?string $category, ?string $productType, string $expected) {
    expect(ValidationCaseType::forTrial($category, $productType))->toBe($expected);
})->with([
    'NPD sales forecast' => ['Trial NPD base on sales forecast', 'Tube', 'NPD'],
    'NPD under development' => ['Trial NPD under development', 'Tube', 'NPD'],
    'Validation is improvement' => ['Validation', 'Tube', 'IMP'],
    'MPT is improvement' => ['MPT', 'Tube', 'IMP'],
    'Filling is improvement' => ['Filling', 'Tube', 'IMP'],
    'Rework is improvement' => ['Rework', 'Tube', 'IMP'],
    'Mixing beats NPD' => ['Trial NPD base on sales forecast', 'Mixing', 'MIX'],
    'Mixing beats Validation' => ['Validation', 'mixing', 'MIX'],
]);

test('trial results are derived from status, NOT OK and client approval', function () {
    $product = vdProduct();

    expect(TrialOutcome::result(vdTrial($product), false, false))->toBe('Pass');
    expect(TrialOutcome::result(vdTrial($product), true, true))->toBe('Pass with Approval Client');
    expect(TrialOutcome::result(vdTrial($product), true, false))->toBe('Re-trial');
    expect(TrialOutcome::result(vdTrial($product, ['progress_status' => 'Need Revision']), false, false))->toBe('Re-trial');
    expect(TrialOutcome::result(vdTrial($product, ['progress_status' => 'Rejected']), false, false))->toBe('Fail');
    expect(TrialOutcome::result(vdTrial($product, ['progress_status' => 'In Review']), false, false))->toBe('Pending');

    expect(TrialOutcome::isAttempt(vdTrial($product, ['progress_status' => 'Draft'])))->toBeFalse();
    expect(TrialOutcome::isAttempt(vdTrial($product)))->toBeTrue();
});

test('the backfill groups trials into cases per type and product and is idempotent', function () {
    $tube = vdProduct('Cream Tube', 'FG-1');
    $bottle = vdProduct('Serum Bottle', 'FG-2');

    $first = vdTrial($tube, ['created_at' => '2026-10-05 08:00:00']);
    $retrial = vdTrial($tube, ['created_at' => '2026-11-03 08:00:00', 'progress_status' => 'Need Revision']);
    $draft = vdTrial($tube, ['created_at' => '2026-11-20 08:00:00', 'progress_status' => 'Draft']);
    $other = vdTrial($bottle, ['created_at' => '2026-11-04 08:00:00', 'validation_category' => 'Validation']);
    $mixing = vdTrial($tube, ['created_at' => '2026-11-05 08:00:00', 'product_type' => 'Mixing']);

    $this->artisan('validation:backfill-cases')->assertSuccessful();

    expect(ValidationCase::count())->toBe(3);

    $npd = ValidationCase::where('case_type', 'NPD')->firstOrFail();
    expect($npd->case_no)->toBe('VAL-NPD-2610-001');
    expect($npd->product_id)->toBe($tube->id);
    expect(ValidationCase::where('case_type', 'IMP')->value('case_no'))->toBe('VAL-IMP-2611-001');
    expect(ValidationCase::where('case_type', 'MIX')->value('case_no'))->toBe('VAL-MIX-2611-001');

    $attempt = fn (Trial $t) => ValidationCaseLink::where('kind', 'trial')->where('ref_key', (string) $t->id)->value('attempt_no');
    expect($attempt($first))->toBe(1);
    expect($attempt($retrial))->toBe(2);
    expect($attempt($draft))->toBeNull();
    expect($attempt($other))->toBe(1);
    expect($attempt($mixing))->toBe(1);

    // Old trials get H-1 default dates, flagged so the KPI can skip them.
    $meta = ValidationTrialMeta::where('trial_id', $first->id)->firstOrFail();
    expect($meta->pm_ready_date->toDateString())->toBe('2026-10-04');
    expect($meta->dates_defaulted)->toBeTrue();

    // Second run changes nothing.
    $this->artisan('validation:backfill-cases')->assertSuccessful();
    expect(ValidationCase::count())->toBe(3);
    expect(ValidationCaseLink::where('kind', 'trial')->count())->toBe(5);
    expect(ValidationTrialMeta::count())->toBe(5);
});

test('the backfill dry run writes nothing and never touches trials_header', function () {
    $product = vdProduct();
    $trial = vdTrial($product);
    $before = DB::table('trials_header')->where('id', $trial->id)->first();

    $this->artisan('validation:backfill-cases --dry-run')->assertSuccessful();

    expect(ValidationCase::count())->toBe(0);
    expect(ValidationCaseLink::count())->toBe(0);
    expect(ValidationTrialMeta::count())->toBe(0);
    expect(DB::table('vd_case_counters')->count())->toBe(0);

    $this->artisan('validation:backfill-cases')->assertSuccessful();
    expect(DB::table('trials_header')->where('id', $trial->id)->first())->toEqual($before);
});

test('project links come from linked stages or a single-case product, never from guessing', function () {
    $user = User::factory()->role('Staff')->create();
    $productA = vdProduct('Product A', 'FG-A');
    $productB = vdProduct('Product B', 'FG-B');

    $trialA = vdTrial($productA);
    vdTrial($productB);
    // Product B also has an improvement Case, so a product-based link is ambiguous.
    vdTrial($productB, ['validation_category' => 'Validation']);

    $viaStage = Project::create(['name' => 'P1', 'code' => 'PRJ-1', 'status' => 'On Progress', 'pic_name' => 'x', 'created_by_user_id' => $user->id]);
    $stage = ProjectStage::create(['project_id' => $viaStage->id, 'name' => 'Validasi', 'stage_type' => 'Validation', 'status' => 'Completed', 'progress' => 100, 'trial_id' => $trialA->id]);
    $ambiguous = Project::create(['name' => 'P2', 'code' => 'PRJ-2', 'status' => 'On Progress', 'pic_name' => 'x', 'product_id' => $productB->id, 'created_by_user_id' => $user->id]);
    $byName = Project::create(['name' => 'Product A', 'code' => 'PRJ-3', 'status' => 'On Progress', 'pic_name' => 'x', 'created_by_user_id' => $user->id]);

    $this->artisan('validation:backfill-cases')->assertSuccessful();

    $caseA = ValidationCase::where('product_id', $productA->id)->firstOrFail();
    expect(ValidationCaseLink::where('kind', 'project')->where('ref_key', (string) $viaStage->id)->value('case_id'))->toBe($caseA->id);
    expect(ValidationCaseLink::where('kind', 'project')->where('ref_key', (string) $ambiguous->id)->exists())->toBeFalse();
    // An exact-name match is only reported, not linked.
    expect(ValidationCaseLink::where('kind', 'project')->where('ref_key', (string) $byName->id)->exists())->toBeFalse();

    // The completed stage is left exactly as it was.
    $stage->refresh();
    expect($stage->status)->toBe('Completed');
    expect($stage->progress)->toBe(100);
});

test('the journey page reads fg from the master and counts attempts per case', function () {
    $user = User::factory()->role('Staff')->create();
    $product = vdProduct('Cream Tube', 'FG-MASTER');

    $one = vdTrial($product, ['finish_good_code' => 'FG-OLD', 'created_at' => '2026-10-01 08:00:00', 'progress_status' => 'Rejected']);
    $two = vdTrial($product, ['created_at' => '2026-11-01 08:00:00']);
    vdNotOk($two);
    ValidationTrialMeta::create(['trial_id' => $two->id, 'pm_ready_date' => '2026-11-05', 'bulk_ready_date' => '2026-11-07', 'dates_defaulted' => false]);

    $this->artisan('validation:backfill-cases')->assertSuccessful();
    $case = ValidationCase::firstOrFail();

    $this->actingAs($user)->get(route('journey.show', $case))
        ->assertInertia(fn ($page) => $page
            ->component('journey/show')
            ->where('case.finish_good_code', 'FG-MASTER')
            ->where('summary.total', 2)
            ->where('summary.counts.Fail', 1)
            ->where('summary.counts.Re-trial', 1)
            ->where('summary.last_result', 'Re-trial')
            ->where('attempts.0.fg_snapshot_stale', true)
            ->where('attempts.1.fg_snapshot_stale', false)
            ->where('attempts.1.lead_time_days', 3)
            ->where('summary.avg_lead_time_days', 3));

    expect($one->fresh()->finish_good_code)->toBe('FG-OLD');
});

test('the journey list filters by type and search', function () {
    $user = User::factory()->role('Staff')->create();
    vdTrial(vdProduct('Alpha Cream', 'FG-A'));
    vdTrial(vdProduct('Beta Serum', 'FG-B'), ['validation_category' => 'Validation']);
    $this->artisan('validation:backfill-cases')->assertSuccessful();

    $this->actingAs($user)->get(route('journey.index', ['type' => 'IMP']))
        ->assertInertia(fn ($page) => $page->has('cases.data', 1)->where('cases.data.0.product_name', 'Beta Serum'));

    $this->actingAs($user)->get(route('journey.index', ['q' => 'FG-A']))
        ->assertInertia(fn ($page) => $page->has('cases.data', 1)->where('cases.data.0.product_name', 'Alpha Cream'));
});

test('recording client approval turns a Re-trial into Pass with Approval Client', function () {
    $staff = User::factory()->role('Staff')->create();
    $trial = vdTrial(vdProduct());
    vdNotOk($trial);
    $this->artisan('validation:backfill-cases')->assertSuccessful();
    $case = ValidationCase::firstOrFail();

    $this->actingAs($staff)->post(route('journey.client-approval', [$case, $trial]), ['approved' => 1, 'note' => 'Surat client 12/2026'])
        ->assertRedirect();

    expect(ValidationTrialMeta::where('trial_id', $trial->id)->value('client_approved'))->toBeTruthy();

    $this->actingAs($staff)->get(route('journey.show', $case))
        ->assertInertia(fn ($page) => $page->where('attempts.0.result', 'Pass with Approval Client'));

    // A note is required when approving; clearing is allowed.
    $this->actingAs($staff)->post(route('journey.client-approval', [$case, $trial]), ['approved' => 1])
        ->assertSessionHasErrors('note');
    $this->actingAs($staff)->post(route('journey.client-approval', [$case, $trial]), ['approved' => 0])->assertRedirect();
    expect(ValidationTrialMeta::where('trial_id', $trial->id)->value('client_approved'))->toBeFalsy();
});

test('a viewer cannot record client approval and a foreign trial is a 404', function () {
    $staff = User::factory()->role('Staff')->create();
    $viewer = User::factory()->role('Viewer')->create();
    $trial = vdTrial(vdProduct());
    $foreign = vdTrial(vdProduct('Other', 'FG-X'));
    $this->artisan('validation:backfill-cases')->assertSuccessful();
    $case = ValidationCase::where('product_id', $trial->product_id)->firstOrFail();

    $this->actingAs($viewer)->post(route('journey.client-approval', [$case, $trial]), ['approved' => 1, 'note' => 'x'])->assertForbidden();
    $this->actingAs($staff)->post(route('journey.client-approval', [$case, $foreign]), ['approved' => 1, 'note' => 'x'])->assertNotFound();
});

test('realized lead time KPI averages only real NPD dates', function () {
    $product = vdProduct();
    $real = vdTrial($product, ['validation_date' => '2026-11-10']);
    $other = vdTrial(vdProduct('B', 'FG-B'), ['validation_date' => '2026-11-20']);
    $improvement = vdTrial(vdProduct('C', 'FG-C'), ['validation_category' => 'Validation', 'validation_date' => '2026-11-20']);
    $this->artisan('validation:backfill-cases')->assertSuccessful();

    // The backfill defaulted all three; give two NPD trials real dates and one IMP too.
    ValidationTrialMeta::where('trial_id', $real->id)->update(['pm_ready_date' => '2026-11-05', 'bulk_ready_date' => '2026-11-02', 'dates_defaulted' => false]);
    ValidationTrialMeta::where('trial_id', $improvement->id)->update(['pm_ready_date' => '2026-01-01', 'bulk_ready_date' => '2026-01-01', 'dates_defaulted' => false]);

    $kpi = CaseJourney::realizedLeadTimeKpi();
    expect($kpi['avg_days'])->toBe(5.0);
    expect($kpi['samples'])->toBe(1);
    expect($kpi['defaulted'])->toBe(1);

    expect(CaseJourney::realizedLeadTimeKpi(['from' => '2026-12-01', 'to' => '2026-12-31'])['avg_days'])->toBeNull();
    expect($other)->not->toBeNull();
});

test('creating a trial saves the ready dates and puts it under a case', function () {
    $staff = User::factory()->role('Staff')->create();
    $product = vdProduct();
    foreach (['product_type' => 'Tube', 'validation_category' => 'Trial NPD base on sales forecast', 'validation_scope' => 'Retail', 'machine_used' => 'Line A'] as $type => $name) {
        MasterOption::create(['type' => $type, 'name' => $name, 'sort_order' => 0, 'is_active' => true]);
    }

    $this->actingAs($staff)->post(route('trials.store'), [
        'product_id' => $product->id,
        'product_type' => 'Tube',
        'validation_date' => '2026-11-20',
        'validation_category' => 'Trial NPD base on sales forecast',
        'risk_level' => 'Low',
        'validation_scope' => ['Retail'],
        'machine_used' => ['Line A'],
        'estimate_qty' => 100,
        'batch_number' => 'B-1',
        'bulk_code' => 'BULK-1',
        'support_team' => 'PIE',
        'initiated_person_team' => 'Zahra',
        'reason' => 'New',
        'bom' => 'BOM',
        'pm_ready_date' => '2026-11-10',
        'bulk_ready_date' => '2026-11-12',
    ])->assertRedirect();

    $trial = Trial::firstOrFail();
    $meta = ValidationTrialMeta::where('trial_id', $trial->id)->firstOrFail();
    expect($meta->pm_ready_date->toDateString())->toBe('2026-11-10');
    expect($meta->dates_defaulted)->toBeFalse();

    $link = ValidationCaseLink::where('kind', 'trial')->where('ref_key', (string) $trial->id)->firstOrFail();
    $case = ValidationCase::findOrFail($link->case_id);
    expect($case->case_type)->toBe('NPD');
    expect($case->case_no)->toBe('VAL-NPD-'.Carbon::now()->format('ym').'-001');
    // A Draft is linked but not counted as an attempt yet.
    expect($link->attempt_no)->toBeNull();

    // Edit shows the entered dates back.
    $this->actingAs($staff)->get(route('trials.edit', $trial))
        ->assertInertia(fn ($page) => $page->where('trial.pm_ready_date', '2026-11-10')->where('trial.bulk_ready_date', '2026-11-12'));
});
