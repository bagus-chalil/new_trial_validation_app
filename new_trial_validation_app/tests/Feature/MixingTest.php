<?php

use App\Models\ActivityLog;
use App\Models\MasterOption;
use App\Models\Mixing;
use App\Models\MixingFile;
use App\Models\MixingReview;
use App\Models\Product;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Models\ValidationParameter;
use App\Support\ValidationStepConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Trial Validation v2.0 Tahap 4: Mixing as Case kind MIX (own vd_mixings
 * tables, same review + e-signature approval pattern as the Trial).
 */

function mxStaff(): User
{
    return User::factory()->role('Staff')->create();
}

function mxProduct(string $name = 'Base Cream'): Product
{
    $product = Product::firstOrCreate(['product_name' => $name], ['finish_good_code' => 'FG-'.strtoupper(substr(md5($name), 0, 6))]);
    $product->is_active = true;
    $product->save();

    return $product;
}

function mxSetupMasters(): void
{
    MasterOption::firstOrCreate(['type' => 'product_type', 'name' => 'Mixing'], ['sort_order' => 1, 'is_active' => true]);
}

function mxParameter(string $name = 'Viscosity'): ValidationParameter
{
    return ValidationParameter::firstOrCreate(['product_type' => 'Mixing', 'parameter_name' => $name], ['specification' => '1000-2000 cP', 'sort_order' => 1]);
}

function mxHeader(Product $product, array $overrides = []): array
{
    return array_merge([
        'product_id' => $product->id,
        'product_type' => 'Mixing',
        'batch_no' => 'B-2611-01',
        'batch_size_kg' => 500,
        'mixing_machine' => 'Mixer 1',
        'mixing_date' => '2026-11-20',
        'objective' => 'Validate the revised batch record',
        'batch_record_no' => 'BR-100',
        'batch_record_revision' => 'R2',
    ], $overrides);
}

function mxDraft(User $owner, ?Product $product = null): Mixing
{
    mxSetupMasters();
    test()->actingAs($owner)->post(route('mixings.store'), mxHeader($product ?? mxProduct()))->assertRedirect();

    return Mixing::query()->latest('id')->firstOrFail();
}

test('mixing uses a four step wizard without weighing', function () {
    expect(ValidationStepConfig::forKind('mixing'))->toBe(['header', 'validation', 'attachments', 'review'])
        ->and(ValidationStepConfig::forKind('trial'))->toContain('weighing-packaging', 'weighing-filling');
});

test('creating a mixing puts it under a MIX case linked as kind mixing', function () {
    $owner = mxStaff();
    $product = mxProduct();
    $mixing = mxDraft($owner, $product);

    $case = ValidationCase::findOrFail($mixing->case_id);
    expect($case->case_type)->toBe('MIX')
        ->and($case->product_id)->toBe($product->id)
        ->and($mixing->product_name)->toBe('Base Cream')
        ->and($mixing->progress_status)->toBe('Draft')
        ->and(ValidationCaseLink::where('kind', 'mixing')->where('ref_key', (string) $mixing->id)->where('case_id', $case->id)->exists())->toBeTrue()
        ->and(ActivityLog::where('module', 'MIXING')->where('action', 'CREATE')->count())->toBe(1);

    // A second mixing of the same product reuses the Case.
    $second = mxDraft($owner, $product);
    expect($second->case_id)->toBe($case->id);
});

test('a mixing created from a Case is linked to that Case', function () {
    $owner = mxStaff();
    mxSetupMasters();
    $product = mxProduct();
    $case = ValidationCase::create(['case_no' => 'VAL-MIX-2611-099', 'case_type' => 'MIX', 'period' => '2611', 'seq' => 99, 'product_id' => $product->id, 'product_name' => 'x', 'source' => 'manual']);

    $this->actingAs($owner)->post(route('mixings.store'), [...mxHeader($product), 'case_id' => $case->id])->assertRedirect();

    expect(Mixing::latest('id')->first()->case_id)->toBe($case->id);
});

test('header validation rejects a missing batch and an unknown product type', function () {
    mxSetupMasters();
    $owner = mxStaff();

    $this->actingAs($owner)->post(route('mixings.store'), mxHeader(mxProduct(), ['batch_no' => '', 'product_type' => 'Nope']))
        ->assertSessionHasErrors(['batch_no', 'product_type']);
    expect(Mixing::count())->toBe(0);
});

test('only staff can create and only the owner edits a draft', function () {
    mxSetupMasters();
    $this->actingAs(User::factory()->role('Viewer')->create())->post(route('mixings.store'), mxHeader(mxProduct()))->assertForbidden();

    $owner = mxStaff();
    $mixing = mxDraft($owner);

    $this->actingAs(mxStaff())->put(route('mixings.update', $mixing), mxHeader(mxProduct('Other')))->assertForbidden();
    $this->actingAs($owner)->put(route('mixings.update', $mixing), mxHeader(mxProduct('Other'), ['batch_no' => 'B-2']))->assertRedirect();
    expect($mixing->fresh()->batch_no)->toBe('B-2')->and($mixing->fresh()->product_name)->toBe('Other');
});

test('a draft is hidden from other users, a submitted mixing is readable', function () {
    $owner = mxStaff();
    $mixing = mxDraft($owner);

    $this->actingAs(User::factory()->role('Viewer')->create())->get(route('mixings.show', $mixing))->assertForbidden();
    $this->actingAs($owner)->get(route('mixings.show', $mixing))->assertOk();

    $mixing->progress_status = 'In Review';
    $mixing->save();
    $this->actingAs(User::factory()->role('Viewer')->create())->get(route('mixings.show', $mixing))->assertOk();
});

test('validation results are normalised and advance the step', function () {
    $owner = mxStaff();
    $mixing = mxDraft($owner);
    $p1 = mxParameter('Viscosity');
    $p2 = mxParameter('pH');

    $this->actingAs($owner)->put(route('mixings.validation.update', $mixing), ['results' => [
        ['parameter_id' => $p1->id, 'decision' => 'OK', 'result' => '', 'remark' => ''],
        ['parameter_id' => $p2->id, 'decision' => 'N/A', 'result' => 'x', 'remark' => ''],
    ]])->assertRedirect(route('mixings.attachments.edit', $mixing));

    expect($mixing->results()->where('parameter_id', $p1->id)->value('result_value'))->toBe('Conform')
        ->and($mixing->results()->where('parameter_id', $p2->id)->value('result_value'))->toBe('N/A')
        ->and($mixing->fresh()->current_step)->toBe('Attachment');
});

test('validation rejects a missing parameter and a NOT OK row without text, saving nothing', function () {
    $owner = mxStaff();
    $mixing = mxDraft($owner);
    $p1 = mxParameter('Viscosity');
    mxParameter('pH');

    $this->actingAs($owner)->put(route('mixings.validation.update', $mixing), ['results' => [
        ['parameter_id' => $p1->id, 'decision' => 'OK'],
    ]])->assertSessionHasErrors('results');

    $this->actingAs($owner)->put(route('mixings.validation.update', $mixing), ['results' => [
        ['parameter_id' => $p1->id, 'decision' => 'NOT OK', 'result' => '', 'remark' => ''],
    ]])->assertSessionHasErrors('results');

    expect($mixing->results()->count())->toBe(0);
});

test('batch record revision evidence uploads as image or pdf, other files are skipped', function () {
    Storage::fake('local');
    $owner = mxStaff();
    $mixing = mxDraft($owner);

    $this->actingAs($owner)->post(route('mixings.attachments.store', $mixing), [
        'category' => 'Revisi Batch Record',
        'files' => [
            UploadedFile::fake()->create('br-r2.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create('macro.exe', 10, 'application/octet-stream'),
        ],
    ])->assertRedirect();

    $file = MixingFile::where('mixing_id', $mixing->id)->sole();
    expect($file->category)->toBe('Revisi Batch Record')
        ->and($file->original_name)->toBe('br-r2.pdf');
    Storage::disk('local')->assertExists('mixing/'.$mixing->id.'/'.$file->file_name);

    $this->actingAs($owner)->get(route('mixings.files.show', [$mixing, $file]))->assertOk();
    $this->actingAs(mxStaff())->get(route('mixings.files.show', [$mixing, $file]))->assertForbidden();

    $this->actingAs($owner)->delete(route('mixings.attachments.destroy', [$mixing, $file]))->assertRedirect();
    expect(MixingFile::count())->toBe(0);
    Storage::disk('local')->assertMissing('mixing/'.$mixing->id.'/'.$file->file_name);
});

test('an invalid attachment category is rejected', function () {
    Storage::fake('local');
    $owner = mxStaff();
    $mixing = mxDraft($owner);

    $this->actingAs($owner)->post(route('mixings.attachments.store', $mixing), [
        'category' => 'Bogus',
        'files' => [UploadedFile::fake()->image('a.png')],
    ])->assertSessionHasErrors('category');
});

function mxReadyDraft(User $owner): Mixing
{
    $mixing = mxDraft($owner);
    $p = mxParameter('Viscosity');
    test()->actingAs($owner)->put(route('mixings.validation.update', $mixing), ['results' => [
        ['parameter_id' => $p->id, 'decision' => 'OK'],
    ]]);

    return $mixing->fresh();
}

test('submitting for review creates pending reviews and an incomplete mixing is rejected', function () {
    $owner = mxStaff();
    $approver = User::factory()->role('Manager QAC')->create();
    $incomplete = mxDraft($owner);
    mxParameter('Viscosity');

    $this->actingAs($owner)->post(route('mixings.review.submit', $incomplete), ['departments' => ['PROD'], 'approver_user_id' => $approver->id])
        ->assertSessionHasErrors('departments');
    expect(MixingReview::count())->toBe(0);

    $ready = mxReadyDraft($owner);
    $this->actingAs($owner)->post(route('mixings.review.submit', $ready), ['departments' => ['PROD', 'QAC'], 'approver_user_id' => $approver->id])
        ->assertRedirect(route('mixings.show', $ready));

    $ready->refresh();
    expect($ready->progress_status)->toBe('In Review')
        ->and($ready->approver_user_id)->toBe($approver->id)
        ->and(MixingReview::where('mixing_id', $ready->id)->where('status', 'Pending')->count())->toBe(2)
        ->and($ready->pending_with)->toBe('PROD,QAC');

    // Once submitted the owner can no longer edit it.
    $this->actingAs($owner)->put(route('mixings.update', $ready), mxHeader(mxProduct('Z')))->assertForbidden();
});

test('review then approval moves the mixing to approved with the e-signature password', function () {
    $owner = mxStaff();
    $approver = User::factory()->role('Manager QAC')->create();
    $ready = mxReadyDraft($owner);
    $this->actingAs($owner)->post(route('mixings.review.submit', $ready), ['departments' => ['PROD', 'QAC'], 'approver_user_id' => $approver->id]);

    $prod = User::factory()->reviewUnit('PROD')->create();
    $qac = User::factory()->reviewUnit('QAC')->create();
    $prodReview = MixingReview::where('mixing_id', $ready->id)->where('department', 'PROD')->firstOrFail();
    $qacReview = MixingReview::where('mixing_id', $ready->id)->where('department', 'QAC')->firstOrFail();

    // Wrong department and empty comment are refused.
    $this->actingAs($qac)->post(route('mixings.review.save', $prodReview), ['comment' => 'x'])->assertForbidden();
    $this->actingAs($prod)->post(route('mixings.review.save', $prodReview), ['comment' => ''])->assertSessionHasErrors('comment');

    $this->actingAs($prod)->post(route('mixings.review.save', $prodReview), ['comment' => 'PROD ok'])->assertRedirect();
    expect($ready->fresh()->progress_status)->toBe('In Review')->and($ready->fresh()->pending_with)->toBe('QAC');

    // An already reviewed row cannot be reviewed again.
    $this->actingAs($prod)->post(route('mixings.review.save', $prodReview), ['comment' => 'again'])->assertForbidden();

    $this->actingAs($qac)->post(route('mixings.review.save', $qacReview), ['comment' => 'QAC ok'])->assertRedirect();
    expect($ready->fresh()->progress_status)->toBe('Ready for Approval');

    // Wrong password, then a non-approver, then the right decision.
    $this->actingAs($approver)->post(route('mixings.decision', $ready), ['decision' => 'Approved', 'approval_comment' => 'ok', 'signature_password' => 'wrong'])
        ->assertSessionHasErrors('signature_password');
    $this->actingAs(mxStaff())->post(route('mixings.decision', $ready), ['decision' => 'Approved', 'approval_comment' => 'ok', 'signature_password' => 'password'])->assertForbidden();

    $this->actingAs($approver)->post(route('mixings.decision', $ready), ['decision' => 'Approved', 'approval_comment' => 'ok', 'signature_password' => 'password'])->assertRedirect();

    $ready->refresh();
    expect($ready->progress_status)->toBe('Approved')
        ->and($ready->final_decision)->toBe('Approved')
        ->and($ready->approved_by)->toBe($approver->name)
        ->and(ActivityLog::where('module', 'MIXING_APPROVAL')->where('action', 'APPROVED')->count())->toBe(1);
});

test('need revision returns the mixing to the owner and bumps the review round', function () {
    $owner = mxStaff();
    $approver = User::factory()->role('Manager QAC')->create();
    $ready = mxReadyDraft($owner);
    $this->actingAs($owner)->post(route('mixings.review.submit', $ready), ['departments' => ['PROD'], 'approver_user_id' => $approver->id]);
    $prod = User::factory()->reviewUnit('PROD')->create();
    $this->actingAs($prod)->post(route('mixings.review.save', MixingReview::where('mixing_id', $ready->id)->firstOrFail()), ['comment' => 'ok']);

    $this->actingAs($approver)->post(route('mixings.decision', $ready), ['decision' => 'Need Revision', 'approval_comment' => 'fix BR', 'signature_password' => 'password'])->assertRedirect();

    $ready->refresh();
    expect($ready->progress_status)->toBe('Need Revision')->and($ready->revision_no)->toBe(1)->and($ready->currentReviewRound())->toBe(2);

    // The owner can edit and resubmit, which opens round 2.
    $this->actingAs($owner)->put(route('mixings.update', $ready), mxHeader(mxProduct('Base Cream'), ['batch_record_revision' => 'R3']))->assertRedirect();
    $this->actingAs($owner)->post(route('mixings.review.submit', $ready), ['departments' => ['PROD'], 'approver_user_id' => $approver->id])->assertRedirect();
    expect(MixingReview::where('mixing_id', $ready->id)->where('review_round', 2)->where('status', 'Pending')->count())->toBe(1);
});

test('the Product Journey shows the Mixing records of the Case', function () {
    $owner = mxStaff();
    $mixing = mxDraft($owner);

    $this->actingAs($owner)->get(route('journey.show', $mixing->case_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('mixings', 1)
            ->where('mixings.0.mixing_code', $mixing->mixing_code)
            ->where('canCreateMixing', true));
});

test('the mixing list hides other users drafts and filters by status', function () {
    $owner = mxStaff();
    $mine = mxDraft($owner);
    $other = mxDraft(mxStaff(), mxProduct('Other Product'));
    $other->progress_status = 'Approved';
    $other->save();
    mxDraft(mxStaff(), mxProduct('Hidden Draft'));

    $this->actingAs($owner)->get(route('mixings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('mixings.data', 2));

    $this->actingAs($owner)->get(route('mixings.index', ['status' => 'Approved']))
        ->assertInertia(fn ($page) => $page->has('mixings.data', 1)->where('mixings.data.0.mixing_code', $other->mixing_code));

    expect($mine->mixing_code)->not->toBe($other->mixing_code);
});

test('a draft can be deleted by its owner only and disappears', function () {
    $owner = mxStaff();
    $mixing = mxDraft($owner);

    $this->actingAs(mxStaff())->delete(route('mixings.destroy', $mixing))->assertForbidden();
    $this->actingAs($owner)->delete(route('mixings.destroy', $mixing))->assertRedirect(route('mixings.index'));
    $this->actingAs($owner)->get(route('mixings.show', $mixing))->assertNotFound();
});

test('the mixing language files have the same keys in id, en and ko', function () {
    $flatten = function (array $a, string $p = '') use (&$flatten): array {
        $out = [];
        foreach ($a as $k => $v) {
            is_array($v) ? $out += $flatten($v, $p.$k.'.') : $out[$p.$k] = true;
        }

        return $out;
    };

    $id = array_keys($flatten(require lang_path('id/mixing.php')));
    expect(array_keys($flatten(require lang_path('en/mixing.php'))))->toEqual($id)
        ->and(array_keys($flatten(require lang_path('ko/mixing.php'))))->toEqual($id);
});
