<?php

use App\Models\FgMapping;
use App\Models\Mixing;
use App\Models\Product;
use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseClassification;
use App\Services\Validation\CaseJourney;
use App\Services\Validation\CaseNumbering;
use App\Services\Validation\FgMappingStatus;
use App\Support\CaseCohort;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/*
 * Trial Validation v2.0 Tahap 5: classification axes, verified FG mapping,
 * data cohort and the self-approval controls from the QAC SuperApp brief.
 */

function alignProduct(string $name = 'Cream Tube 50ml', string $fg = 'FG-1'): Product
{
    $product = Product::create(['product_name' => $name, 'finish_good_code' => $fg]);
    $product->is_active = true;
    $product->save();

    return $product;
}

function alignTrial(Product $product, array $attributes = []): Trial
{
    $trial = Trial::create(array_merge([
        'trial_code' => 'TRIAL-AL-'.uniqid(),
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

    if (isset($attributes['created_at'])) {
        DB::table('trials_header')->where('id', $trial->id)->update(['created_at' => $attributes['created_at']]);
        $trial->refresh();
    }

    return $trial;
}

function alignCase(string $type = 'NPD'): ValidationCase
{
    return app(CaseNumbering::class)->create($type, '2611', null, 'Align product', 'manual');
}

test('a case without a stored classification shows the defaults derived from its type', function () {
    $user = User::factory()->role('Staff')->create();
    $case = alignCase('MIX');

    $this->actingAs($user)->get(route('journey.show', $case))
        ->assertInertia(fn ($page) => $page
            ->where('classification.process_area', 'Mixing')
            ->where('classification.purpose', 'Improvement')
            ->where('classification.subject', 'Product')
            ->where('classification.derived', true));
});

test('staff can store the four classification axes without changing the case number', function () {
    $user = User::factory()->role('Staff')->create();
    $case = alignCase('NPD');
    $number = $case->case_no;

    $this->actingAs($user)->put(route('journey.classification.update', $case), [
        'process_area' => 'Filling-Packing',
        'purpose' => 'Other',
        'subject' => 'Machine',
        'document_type' => 'Trial Report',
        'other_purpose_note' => 'Line speed test',
    ])->assertRedirect();

    $row = ValidationCaseClassification::where('case_id', $case->id)->firstOrFail();
    expect($row->purpose)->toBe('Other')
        ->and($row->subject)->toBe('Machine')
        ->and($row->other_purpose_note)->toBe('Line speed test');
    expect($case->fresh()->case_no)->toBe($number);

    $this->actingAs($user)->get(route('journey.show', $case))
        ->assertInertia(fn ($page) => $page->where('classification.derived', false)->where('classification.subject', 'Machine'));
});

test('classification rejects unknown values and requires a note for Other', function () {
    $user = User::factory()->role('Staff')->create();
    $case = alignCase();

    $this->actingAs($user)->put(route('journey.classification.update', $case), [
        'process_area' => 'Warehouse', 'purpose' => 'NPD', 'subject' => 'Product',
    ])->assertSessionHasErrors('process_area');

    $this->actingAs($user)->put(route('journey.classification.update', $case), [
        'process_area' => 'Mixing', 'purpose' => 'Other', 'subject' => 'Product',
    ])->assertSessionHasErrors('other_purpose_note');

    expect(ValidationCaseClassification::count())->toBe(0);
});

test('a viewer cannot classify a case', function () {
    $viewer = User::factory()->role('Viewer')->create();

    $this->actingAs($viewer)->put(route('journey.classification.update', alignCase()), [
        'process_area' => 'Mixing', 'purpose' => 'NPD', 'subject' => 'Product',
    ])->assertForbidden();
});

test('the fg import is idempotent, keeps history and proposes a product match', function () {
    $product = alignProduct('Matched Cream', 'FG-777');
    $csv = tempnam(sys_get_temp_dir(), 'fg').'.csv';
    file_put_contents($csv, "fg_code,item_name,effective_date\nFG-777,Matched Cream 50ml,2026-11-01\nFG-888,Unknown Item,\n,,\n");

    $this->artisan('validation:import-fg', ['file' => $csv, '--dry-run' => true])->assertSuccessful();
    expect(FgMapping::count())->toBe(0);

    $this->artisan('validation:import-fg', ['file' => $csv])->assertSuccessful();
    expect(FgMapping::count())->toBe(2);
    expect(FgMapping::where('fg_code', 'FG-777')->first()->product_id)->toBe($product->id);
    expect(FgMapping::where('fg_code', 'FG-888')->first()->product_id)->toBeNull();

    $this->artisan('validation:import-fg', ['file' => $csv])->assertSuccessful();
    expect(FgMapping::count())->toBe(2);

    file_put_contents($csv, "fg_code,item_name,effective_date\nFG-777,Matched Cream 60ml,2026-11-01\n");
    $this->artisan('validation:import-fg', ['file' => $csv])->assertSuccessful();
    $mapping = FgMapping::where('fg_code', 'FG-777')->first();
    expect($mapping->item_name)->toBe('Matched Cream 60ml')
        ->and($mapping->item_name_previous)->toBe('Matched Cream 50ml');

    unlink($csv);
});

test('the fg status is missing, unverified, verified or mismatch', function () {
    $product = alignProduct('Status Cream', 'FG-900');
    $none = alignProduct('No Code', 'N/A');

    expect(FgMappingStatus::forProduct(null)['status'])->toBe('missing');
    expect(FgMappingStatus::forProduct($none)['status'])->toBe('missing');
    expect(FgMappingStatus::forProduct($product)['status'])->toBe('missing');

    $mapping = FgMapping::create(['fg_code' => 'FG-900', 'item_name' => 'Official', 'product_id' => $product->id]);
    expect(FgMappingStatus::forProduct($product)['status'])->toBe('unverified');

    $mapping->update(['verified_at' => now()]);
    expect(FgMappingStatus::forProduct($product)['status'])->toBe('verified');

    $mapping->update(['product_id' => $product->id + 100]);
    expect(FgMappingStatus::forProduct($product)['status'])->toBe('mismatch');
});

test('qa or pie can verify a listed fg mapping but a plain staff cannot', function () {
    $product = alignProduct('Verify Cream', 'FG-910');
    FgMapping::create(['fg_code' => 'FG-910', 'item_name' => 'Official Verify']);
    $case = app(CaseNumbering::class)->create('NPD', '2611', $product->id, $product->product_name, 'manual');

    $staff = User::factory()->role('Staff', 'PRD')->create();
    $this->actingAs($staff)->post(route('journey.fg.verify', $case))->assertForbidden();

    $qa = User::factory()->role('Staff', 'QAC')->create();
    $this->actingAs($qa)->post(route('journey.fg.verify', $case))->assertRedirect();

    $mapping = FgMapping::where('fg_code', 'FG-910')->firstOrFail();
    expect($mapping->verified_at)->not->toBeNull()
        ->and($mapping->product_id)->toBe($product->id)
        ->and($mapping->verified_by_user_id)->toBe($qa->id);
    expect(FgMappingStatus::forProduct($product)['status'])->toBe('verified');
});

test('a backfilled case is historical or eligible by its first trial date, not its own row date', function () {
    $product = alignProduct('Cohort Cream', 'FG-C1');
    alignTrial($product, ['created_at' => '2026-10-20 08:00:00']);
    $product2 = alignProduct('Cohort Cream New', 'FG-C2');
    alignTrial($product2, ['created_at' => '2026-11-20 08:00:00']);

    $this->artisan('validation:backfill-cases')->assertSuccessful();

    $old = ValidationCase::where('product_id', $product->id)->firstOrFail();
    $new = ValidationCase::where('product_id', $product2->id)->firstOrFail();
    expect(CaseCohort::forCase($old))->toBe('historical')
        ->and(CaseCohort::forCase($new))->toBe('eligible');

    $user = User::factory()->role('Staff')->create();
    $this->actingAs($user)->get(route('journey.index', ['cohort' => 'eligible']))
        ->assertInertia(fn ($page) => $page->has('cases.data', 1)->where('cases.data.0.id', $new->id));
    $this->actingAs($user)->get(route('journey.index', ['cohort' => 'historical']))
        ->assertInertia(fn ($page) => $page->has('cases.data', 1)->where('cases.data.0.id', $old->id));
});

test('nobody approves a trial they prepared, admin included, unless the guard is off', function () {
    $admin = User::factory()->role('Admin')->create();
    $trial = Trial::create([
        'trial_code' => 'TRIAL-SELF-1', 'product_name' => 'P', 'product_type' => 'Tube',
        'progress_status' => 'Ready for Approval', 'current_step' => 'Approval',
        'created_by' => $admin->email, 'batch_number' => 'B', 'bulk_code' => 'C', 'support_team' => 'QA',
        'initiated_person_team' => 'S', 'reason' => 'r', 'bom' => 'b', 'revision_no' => 0,
        'approver_user_id' => $admin->id,
    ]);

    expect($admin->can('approve', $trial))->toBeFalse();

    config(['validation_v2.prevent_self_approval' => false]);
    expect($admin->can('approve', $trial))->toBeTrue();
});

test('the assigned approver who is not the preparer can still approve', function () {
    $approver = User::factory()->role('Manager QAC')->create();
    $trial = Trial::create([
        'trial_code' => 'TRIAL-SELF-2', 'product_name' => 'P', 'product_type' => 'Tube',
        'progress_status' => 'Ready for Approval', 'current_step' => 'Approval',
        'created_by' => 'someone.else@local.test', 'batch_number' => 'B', 'bulk_code' => 'C', 'support_team' => 'QA',
        'initiated_person_team' => 'S', 'reason' => 'r', 'bom' => 'b', 'revision_no' => 0,
        'approver_user_id' => $approver->id,
    ]);

    expect($approver->can('approve', $trial))->toBeTrue();
});

test('a mixing preparer cannot approve their own mixing', function () {
    $user = User::factory()->role('Manager QAC')->create();
    $mixing = new Mixing;
    $mixing->forceFill([
        'progress_status' => 'Ready for Approval', 'created_by' => $user->email, 'approver_user_id' => $user->id,
    ]);

    expect($user->can('approve', $mixing))->toBeFalse();
});

test('the lead time kpi only counts the eligible cohort', function () {
    $product = alignProduct('Kpi Cream', 'FG-K1');
    $old = alignTrial($product, ['created_at' => '2026-10-20 08:00:00', 'validation_date' => '2026-10-25']);
    $this->artisan('validation:backfill-cases')->assertSuccessful();
    DB::table('vd_trial_meta')->where('trial_id', $old->id)->update([
        'pm_ready_date' => '2026-10-20', 'bulk_ready_date' => '2026-10-21', 'dates_defaulted' => false,
    ]);

    expect(CaseJourney::realizedLeadTimeKpi()['avg_days'])->toBeNull();
});
