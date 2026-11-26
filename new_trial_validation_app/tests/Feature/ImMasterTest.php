<?php

use App\Actions\ImMaster\ImRelease;
use App\Models\ImBrandCode;
use App\Models\ImMaster;
use App\Models\ImNumber;
use App\Models\ImParameter;
use App\Models\ImRevision;
use App\Models\ImSignoff;
use App\Models\Product;
use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/*
 * Trial Validation v2.0 Tahap 6: IM master owned by QA (one per FG Code, N
 * revisions, QA review + approval with e-signature, Effective / Superseded,
 * release date, evidence, files, PDF).
 */

function imQa(string $name = 'QA User'): User
{
    return User::factory()->role('Staff', 'QA')->create(['name' => $name]);
}

function imProduct(string $fg = '631208004'): Product
{
    $product = Product::create(['product_name' => 'Bhumi Calming Cleanser 100ml', 'finish_good_code' => $fg]);
    $product->is_active = true;
    $product->save();

    return $product;
}

function imApprovedTrial(Product $product, string $status = 'Approved'): Trial
{
    return Trial::create([
        'trial_code' => 'TRIAL-IM-'.uniqid(),
        'product_id' => $product->id,
        'product_name' => $product->product_name,
        'finish_good_code' => $product->finish_good_code,
        'product_type' => 'Tube',
        'validation_category' => 'Trial NPD base on sales forecast',
        'progress_status' => $status,
        'created_by' => 'owner@example.test',
    ]);
}

/** A revision with a complete worksheet, still Draft. */
function imDraft(User $qa, string $fg = '631208004'): ImRevision
{
    ImBrandCode::firstOrCreate(['code' => 'BHM'], ['name' => 'Bhumi', 'is_active' => true]);
    $product = Product::query()->where('finish_good_code', $fg)->first() ?? imProduct($fg);

    test()->actingAs($qa)->post(route('validation.im-masters.store'), [
        'fg_code' => $fg, 'brand_code' => 'BHM', 'product_part' => 'bhumi calming cleanser 100ml',
        'product_id' => $product->id, 'customer' => 'PT Riza',
    ])->assertRedirect();

    $revision = ImRevision::query()->latest('id')->firstOrFail();
    imFill($qa, $revision, $product);

    return $revision->fresh();
}

function imFill(User $qa, ImRevision $revision, Product $product): void
{
    $trial = imApprovedTrial($product);

    test()->actingAs($qa)->put(route('validation.im-revisions.update', $revision->id), [
        'description' => 'BHM Glacier Water Calming Cleanser 100ml',
        'line_label' => 'LINE SKINCARE - MACHINE FILLING TUBE (1 NOZZLE)',
        'issue_date' => now()->toDateString(),
        'effective_from' => now()->toDateString(),
        'revision_reason' => $revision->revision_no > 1 ? 'Address change' : '',
        'bulk_wip_refs' => ['6312080013'],
        'content' => ['master_box_qty' => '48 pcs'],
        'parameters' => [
            ['section' => 'bom', 'code' => '631208001711', 'name' => 'Oval Tube + Fliptop Cap', 'category' => 'Primary Packaging'],
            ['section' => 'weight', 'name' => 'BHM Glacier Water Calming Cleanser', 'code' => '6312080013', 'category' => '631208004', 'value_min' => '101.8', 'value_max' => '102.8', 'unit' => 'Gram'],
            ['section' => 'surveillance_initial', 'name' => 'Cek kelengkapan', 'description' => 'Bandingkan tag', 'specification' => 'Sesuai', 'frequency' => 'Tiap ganti material'],
        ],
        'evidence' => [['kind' => 'trial', 'trial_id' => $trial->id]],
    ])->assertSessionHasNoErrors();
}

function imRelease(ImRevision $revision, User $preparer, User $reviewer, User $approver): ImRevision
{
    test()->actingAs($preparer)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    $signoff = ImSignoff::query()->where('revision_id', $revision->id)->where('stage', 'review')->firstOrFail();
    test()->actingAs($reviewer)->post(route('validation.im-signoffs.review', $signoff->id), [
        'comment' => 'OK', 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    test()->actingAs($approver)->post(route('validation.im-revisions.decision', $revision->id), [
        'decision' => 'Approved', 'approval_comment' => 'Approved by QA', 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    return $revision->fresh();
}

test('only QA can start an IM master and the first revision is a Draft with an issued number', function () {
    ImBrandCode::firstOrCreate(['code' => 'BHM'], ['name' => 'Bhumi', 'is_active' => true]);

    $other = User::factory()->role('Staff', 'PIE')->create();
    $this->actingAs($other)->post(route('validation.im-masters.store'), ['fg_code' => '1', 'brand_code' => 'BHM', 'product_part' => 'x'])->assertForbidden();

    $revision = imDraft(imQa());

    expect($revision->status)->toBe('Draft')
        ->and($revision->revision_no)->toBe(1)
        ->and($revision->im_number)->toBe('IM-BHM-BHUMI CALMING CLEANSER 100ML')
        ->and(ImNumber::query()->where('number', $revision->im_number)->value('status'))->toBe('Active')
        ->and(ImMaster::query()->value('fg_code'))->toBe('631208004');
});

test('one master per FG Code', function () {
    $qa = imQa();
    imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im-masters.store'), [
        'fg_code' => '631208004', 'brand_code' => 'BHM', 'product_part' => 'other',
    ])->assertSessionHasErrors('fg_code');

    expect(ImMaster::query()->count())->toBe(1);
});

test('an issued number from the register can be adopted when onboarding an existing IM', function () {
    $qa = imQa();
    ImBrandCode::firstOrCreate(['code' => 'ABL'], ['name' => 'Abel', 'is_active' => true]);
    $number = ImNumber::create([
        'number' => 'IM-ABL-SCULPT CALM LILY DEODORANT 25ML', 'brand_code' => 'ABL', 'product_part' => 'SCULPT CALM LILY DEODORANT 25ML',
        'version_no' => 1, 'status' => 'Active', 'issued_by_name' => 'QA', 'fg_codes' => ['651000001'],
    ]);

    $this->actingAs($qa)->post(route('validation.im-masters.store'), [
        'fg_code' => '651000001', 'existing_im_number_id' => $number->id, 'legacy_reference' => 'FR.QAC.050.01 signed 2025-01-10',
    ])->assertRedirect();

    $revision = ImRevision::query()->firstOrFail();
    expect($revision->im_number_id)->toBe($number->id)->and($revision->is_onboarded)->toBeTrue();

    // The same number cannot start a second master.
    $this->actingAs($qa)->post(route('validation.im-masters.store'), [
        'fg_code' => '651000002', 'existing_im_number_id' => $number->id,
    ])->assertSessionHasErrors('existing_im_number_id');
});

test('worksheet save stores structured parameters and refuses a non-approved trial as evidence', function () {
    $qa = imQa();
    $revision = imDraft($qa);

    expect(ImParameter::query()->where('revision_id', $revision->id)->count())->toBe(3)
        ->and((float) ImParameter::query()->where('section', 'weight')->value('value_min'))->toBe(101.8)
        ->and($revision->content['master_box_qty'])->toBe('48 pcs')
        ->and($revision->master->bulk_wip_refs)->toBe(['6312080013']);

    $draftTrial = imApprovedTrial(Product::query()->firstOrFail(), 'Draft');
    $this->actingAs($qa)->put(route('validation.im-revisions.update', $revision->id), [
        'evidence' => [['kind' => 'trial', 'trial_id' => $draftTrial->id]],
    ])->assertSessionHasErrors('evidence.0.trial_id');
});

test('submit is blocked while the worksheet is incomplete', function () {
    $qa = imQa();
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    ImBrandCode::firstOrCreate(['code' => 'BHM'], ['name' => 'Bhumi', 'is_active' => true]);
    $this->actingAs($qa)->post(route('validation.im-masters.store'), ['fg_code' => '9', 'brand_code' => 'BHM', 'product_part' => 'p'])->assertRedirect();
    $revision = ImRevision::query()->firstOrFail();

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ])->assertSessionHasErrors('reviewer_ids');

    expect($revision->fresh()->status)->toBe('Draft');
});

test('the QA chain: sign, review, approve makes the revision Effective', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $revision = imDraft($qa);

    // Wrong password never signs.
    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'wrong',
    ])->assertSessionHasErrors('signature_password');
    expect($revision->fresh()->status)->toBe('Draft');

    $revision = imRelease($revision, $qa, $reviewer, $approver);

    expect($revision->status)->toBe('Effective')
        ->and($revision->approved_by)->toBe('Approver')
        ->and($revision->effective_at)->not->toBeNull()
        ->and(ImSignoff::query()->where('revision_id', $revision->id)->where('status', 'Signed')->count())->toBe(3);
});

test('nobody in the chain can be the preparer, and the approver cannot also review', function () {
    $qa = imQa('Preparer');
    $other = imQa('Other');
    $revision = imDraft($qa);

    // The preparer is never offered, and posting them is refused.
    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$qa->id], 'approver_user_id' => $other->id, 'signature_password' => 'password',
    ])->assertSessionHasErrors('reviewer_ids');

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$other->id], 'approver_user_id' => $other->id, 'signature_password' => 'password',
    ])->assertSessionHasErrors('approver_user_id');

    // A non-QA account cannot be chosen.
    $outsider = User::factory()->role('Staff', 'PIE')->create();
    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$outsider->id], 'approver_user_id' => $other->id, 'signature_password' => 'password',
    ])->assertSessionHasErrors('reviewer_ids');

    expect($revision->fresh()->status)->toBe('Draft');
});

test('only the assigned QA reviewer and approver may sign their slot', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $stranger = imQa('Stranger');
    $revision = imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    $signoff = ImSignoff::query()->where('stage', 'review')->firstOrFail();
    $this->actingAs($stranger)->post(route('validation.im-signoffs.review', $signoff->id), ['comment' => 'x', 'signature_password' => 'password'])->assertForbidden();
    $this->actingAs($qa)->post(route('validation.im-signoffs.review', $signoff->id), ['comment' => 'x', 'signature_password' => 'password'])->assertForbidden();

    // Approver cannot decide before the review round is complete.
    $this->actingAs($approver)->post(route('validation.im-revisions.decision', $revision->id), [
        'decision' => 'Approved', 'approval_comment' => 'x', 'signature_password' => 'password',
    ])->assertForbidden();

    $this->actingAs($reviewer)->post(route('validation.im-signoffs.review', $signoff->id), ['comment' => 'ok', 'signature_password' => 'password'])->assertSessionHasNoErrors();
    expect($revision->fresh()->status)->toBe('Ready for Approval');

    $this->actingAs($stranger)->post(route('validation.im-revisions.decision', $revision->id), [
        'decision' => 'Approved', 'approval_comment' => 'x', 'signature_password' => 'password',
    ])->assertForbidden();
});

test('a worksheet in the QA chain is locked, and a returned one becomes editable again', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $revision = imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ]);

    $this->actingAs($qa)->put(route('validation.im-revisions.update', $revision->id), ['description' => 'changed'])->assertForbidden();

    $signoff = ImSignoff::query()->where('stage', 'review')->firstOrFail();
    $this->actingAs($reviewer)->post(route('validation.im-signoffs.review', $signoff->id), ['comment' => 'ok', 'signature_password' => 'password']);

    $this->actingAs($approver)->post(route('validation.im-revisions.decision', $revision->id), [
        'decision' => 'Need Revision', 'approval_comment' => 'Fix the weight limits', 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    $revision->refresh();
    expect($revision->status)->toBe('Need Revision')->and($revision->review_round)->toBe(2);

    $this->actingAs($qa)->put(route('validation.im-revisions.update', $revision->id), ['description' => 'Fixed'])->assertSessionHasNoErrors();
    expect($revision->fresh()->description)->toBe('Fixed');
});

test('a new revision is a Draft, the old one stays Effective until the new one is released', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $first = imRelease(imDraft($qa), $qa, $reviewer, $approver);
    $master = $first->master;

    // Only one open revision at a time and a reason is mandatory.
    $this->actingAs($qa)->post(route('validation.im-masters.revisions.store', $master->id), ['revision_reason' => ''])->assertSessionHasErrors('revision_reason');
    $this->actingAs($qa)->post(route('validation.im-masters.revisions.store', $master->id), ['revision_reason' => 'Address on UB'])->assertRedirect();

    $second = ImRevision::query()->where('master_id', $master->id)->where('revision_no', 2)->firstOrFail();
    expect($second->status)->toBe('Draft')
        ->and($second->im_number)->toBe('IM-BHM-BHUMI CALMING CLEANSER 100ML V2')
        ->and($second->replaces_number)->toBe($first->im_number)
        ->and(ImParameter::query()->where('revision_id', $second->id)->count())->toBe(3)
        ->and($first->fresh()->status)->toBe('Effective')
        ->and(ImNumber::query()->where('number', $first->im_number)->value('status'))->toBe('Active');

    $this->actingAs($qa)->post(route('validation.im-masters.revisions.store', $master->id), ['revision_reason' => 'Another'])->assertSessionHasErrors('revision_reason');
});

test('releasing a revision supersedes the previous Effective one, never two at once', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $first = imRelease(imDraft($qa), $qa, $reviewer, $approver);

    $this->actingAs($qa)->post(route('validation.im-masters.revisions.store', $first->master_id), ['revision_reason' => 'Address on UB']);
    $second = ImRevision::query()->where('revision_no', 2)->firstOrFail();
    imFill($qa, $second, Product::query()->firstOrFail());
    $second = imRelease($second->fresh(), $qa, $reviewer, $approver);

    expect($second->status)->toBe('Effective')
        ->and($first->fresh()->status)->toBe('Superseded')
        ->and($first->fresh()->superseded_at)->not->toBeNull()
        ->and(ImRevision::query()->where('master_id', $first->master_id)->where('status', 'Effective')->count())->toBe(1)
        ->and(ImNumber::query()->where('number', $first->im_number)->value('status'))->toBe('Superseded');
});

test('an approved future revision stays Scheduled until its release date, then the command makes it Effective', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $first = imRelease(imDraft($qa), $qa, $reviewer, $approver);

    $this->actingAs($qa)->post(route('validation.im-masters.revisions.store', $first->master_id), ['revision_reason' => 'Future change']);
    $second = ImRevision::query()->where('revision_no', 2)->firstOrFail();
    imFill($qa, $second, Product::query()->firstOrFail());
    $second->refresh();
    $second->effective_from = now()->addDays(10)->toDateString();
    $second->save();

    $second = imRelease($second, $qa, $reviewer, $approver);
    expect($second->status)->toBe('Scheduled')->and($first->fresh()->status)->toBe('Effective');

    // Not due yet: nothing changes.
    expect(app(ImRelease::class)->releaseDue())->toBe(0);

    $this->travel(11)->days();
    $this->artisan('validation:im-release')->assertSuccessful();

    expect($second->fresh()->status)->toBe('Effective')->and($first->fresh()->status)->toBe('Superseded');
});

test('a rejected revision voids its number and never becomes Effective', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $revision = imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ]);
    $signoff = ImSignoff::query()->where('stage', 'review')->firstOrFail();
    $this->actingAs($reviewer)->post(route('validation.im-signoffs.review', $signoff->id), ['comment' => 'ok', 'signature_password' => 'password']);
    $this->actingAs($approver)->post(route('validation.im-revisions.decision', $revision->id), [
        'decision' => 'Rejected', 'approval_comment' => 'Wrong product', 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    expect($revision->fresh()->status)->toBe('Rejected')
        ->and(ImNumber::query()->where('number', $revision->im_number)->value('status'))->toBe('Void')
        ->and(ImRevision::query()->where('status', 'Effective')->count())->toBe(0);
});

test('Draft and in-review IMs are only visible to QA, Effective ones to everybody', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $outsider = User::factory()->role('Staff', 'PIE')->create();
    $revision = imDraft($qa);

    $this->actingAs($outsider)->get(route('validation.im-revisions.show', $revision->id))->assertForbidden();
    $this->actingAs($outsider)->get(route('validation.im-masters.show', $revision->master_id))->assertForbidden();
    $this->actingAs($outsider)->get(route('validation.im-revisions.pdf', $revision->id))->assertForbidden();

    imRelease($revision, $qa, $reviewer, $approver);

    $this->actingAs($outsider)->get(route('validation.im-revisions.show', $revision->id))->assertOk();
    $this->actingAs($outsider)->get(route('validation.im-masters.index'))->assertOk();
});

test('the PDF mirrors the paper IM and marks a non-effective revision', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $revision = imDraft($qa);

    $draft = $this->actingAs($qa)->get(route('validation.im-revisions.pdf', $revision->id));
    $draft->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($draft->getContent())->toContain('INSTRUKSI MUTU')->toContain($revision->im_number)->toContain('631208004')->toContain('101.8')->toContain('BUKAN IM EFEKTIF');

    imRelease($revision, $qa, $reviewer, $approver);
    $effective = $this->actingAs($qa)->get(route('validation.im-revisions.pdf', $revision->id))->getContent();
    expect($effective)->not->toContain('BUKAN IM EFEKTIF')->toContain('Approver');
});

test('files are stored privately, images only except for source documents', function () {
    Storage::fake('local');
    $qa = imQa();
    $revision = imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im-revisions.files.store', $revision->id), [
        'category' => 'appearance_filling', 'caption' => 'Tube V2',
        'files' => [UploadedFile::fake()->image('tube.png'), UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    expect($revision->files()->count())->toBe(1);
    $file = $revision->files()->firstOrFail();
    Storage::disk('local')->assertExists('im/'.$revision->id.'/'.$file->file_name);

    $this->actingAs($qa)->post(route('validation.im-revisions.files.store', $revision->id), [
        'category' => 'source_document', 'files' => [UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf')],
    ]);
    expect($revision->files()->count())->toBe(2);

    $this->actingAs($qa)->delete(route('validation.im-revisions.files.destroy', [$revision->id, $file->id]))->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing('im/'.$revision->id.'/'.$file->file_name);
});

test('the IM number can be linked to a Case of the same product', function () {
    $qa = imQa();
    $product = imProduct();
    $case = ValidationCase::create([
        'case_no' => 'VAL-NPD-2612-001', 'case_type' => 'NPD', 'period' => '2612', 'seq' => 1,
        'product_id' => $product->id, 'product_name' => $product->product_name, 'source' => 'manual',
    ]);
    $revision = imDraft($qa);

    $this->actingAs($qa)->put(route('validation.im-revisions.update', $revision->id), ['case_id' => $case->id])->assertSessionHasNoErrors();

    expect(ValidationCaseLink::query()->where('kind', 'im')->where('ref_key', $revision->im_number)->value('case_id'))->toBe($case->id);
});

test('discarding a Draft voids its number and removes an empty master', function () {
    $qa = imQa();
    $revision = imDraft($qa);

    $this->actingAs($qa)->delete(route('validation.im-revisions.destroy', $revision->id))->assertRedirect(route('validation.im-masters.index'));

    expect(ImRevision::query()->count())->toBe(0)
        ->and(ImMaster::query()->count())->toBe(0)
        ->and(ImNumber::query()->where('number', $revision->im_number)->value('status'))->toBe('Void');
});

test('the old number register cannot revise or void a number owned by an IM master', function () {
    $qa = imQa();
    $revision = imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im.revise', $revision->im_number_id), ['revision_reason' => 'x'])->assertStatus(422);
    $this->actingAs($qa)->post(route('validation.im.void', $revision->im_number_id), ['reason' => 'x'])->assertStatus(422);

    expect(ImNumber::query()->find($revision->im_number_id)->status)->toBe('Active');
});

test('the snapshot payload is a frozen copy of the rules IPC will pin', function () {
    $qa = imQa();
    $revision = imDraft($qa);

    $snapshot = $revision->snapshotPayload();

    expect($snapshot['im_number'])->toBe($revision->im_number)
        ->and(Arr::pluck($snapshot['parameters'], 'section'))->toContain('weight')
        ->and((float) collect($snapshot['parameters'])->firstWhere('section', 'weight')['value_max'])->toBe(102.8);
});

test('im_master language files have the same keys in id, en and ko', function () {
    $flatten = fn (array $a) => Arr::dot($a);
    $id = array_keys($flatten(require lang_path('id/im_master.php')));

    expect(array_keys($flatten(require lang_path('en/im_master.php'))))->toEqual($id)
        ->and(array_keys($flatten(require lang_path('ko/im_master.php'))))->toEqual($id);
});

test('a read-only Viewer in a QA department can neither start, edit nor be picked as a signer', function () {
    $viewer = User::factory()->role('Viewer', 'QA')->create(['name' => 'QA Viewer']);
    $qa = imQa('Preparer');
    $other = imQa('Other');
    ImBrandCode::firstOrCreate(['code' => 'BHM'], ['name' => 'Bhumi', 'is_active' => true]);

    $this->actingAs($viewer)->post(route('validation.im-masters.store'), ['fg_code' => '1', 'brand_code' => 'BHM', 'product_part' => 'x'])->assertForbidden();

    $revision = imDraft($qa);
    $this->actingAs($viewer)->put(route('validation.im-revisions.update', $revision->id), ['description' => 'x'])->assertForbidden();

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$viewer->id], 'approver_user_id' => $other->id, 'signature_password' => 'password',
    ])->assertSessionHasErrors('reviewer_ids');
});

test('the sign-off sheet keeps the paper titles and creates the Knowledge by rows', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $revision = imDraft($qa);

    // A title outside the configured list is refused.
    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'reviewer_labels' => [$reviewer->id => 'Janitor'],
        'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ])->assertSessionHasErrors('reviewer_labels.'.$reviewer->id);

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'reviewer_labels' => [$reviewer->id => 'QA Part Leader'],
        'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    $rows = ImSignoff::query()->where('revision_id', $revision->id)->get();
    expect($rows->firstWhere('stage', 'prepared')->role_label)->toBe('QA Staff')
        ->and($rows->firstWhere('stage', 'review')->role_label)->toBe('QA Part Leader')
        ->and($rows->firstWhere('stage', 'approved')->role_label)->toBe('QAC Leader')
        ->and($rows->where('stage', 'knowledge')->pluck('role_label')->all())
        ->toBe(['QC Part Leader', 'RNI Team', 'Production Leader', 'PPIC Leader']);
});

test('only a member of the matching team signs a Knowledge by row, and it never blocks release', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $rni = User::factory()->role('Staff', 'RNI')->create(['name' => 'RNI Member']);
    $prod = User::factory()->role('Staff', 'PRD')->create(['name' => 'Prod Member']);
    $revision = imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    $slot = ImSignoff::query()->where('revision_id', $revision->id)->where('role_label', 'RNI Team')->firstOrFail();

    // Wrong team, the preparer and a wrong password are all refused.
    $this->actingAs($prod)->post(route('validation.im-signoffs.acknowledge', $slot->id), ['signature_password' => 'password'])->assertForbidden();
    $this->actingAs($qa)->post(route('validation.im-signoffs.acknowledge', $slot->id), ['signature_password' => 'password'])->assertForbidden();
    $this->actingAs($rni)->post(route('validation.im-signoffs.acknowledge', $slot->id), ['signature_password' => 'nope'])->assertSessionHasErrors('signature_password');

    $this->actingAs($rni)->post(route('validation.im-signoffs.acknowledge', $slot->id), [
        'comment' => 'Dipahami', 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    expect($slot->fresh()->status)->toBe('Signed')
        ->and($slot->fresh()->user_name)->toBe('RNI Member')
        ->and($slot->fresh()->comment)->toBe('Dipahami');

    // A second signature on the same row is refused.
    $this->actingAs($rni)->post(route('validation.im-signoffs.acknowledge', $slot->id), ['signature_password' => 'password'])->assertForbidden();

    // The other three rows stay Pending and the IM still goes Effective.
    $row = ImSignoff::query()->where('revision_id', $revision->id)->where('stage', 'review')->firstOrFail();
    $this->actingAs($reviewer)->post(route('validation.im-signoffs.review', $row->id), ['comment' => 'OK', 'signature_password' => 'password'])->assertSessionHasNoErrors();
    $this->actingAs($approver)->post(route('validation.im-revisions.decision', $revision->id), [
        'decision' => 'Approved', 'approval_comment' => 'OK', 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    expect($revision->fresh()->status)->toBe('Effective')
        ->and(ImSignoff::query()->where('revision_id', $revision->id)->where('stage', 'knowledge')->where('status', 'Pending')->count())->toBe(3);

    // The signed row is printed on the sheet together with the paper titles.
    $pdf = $this->actingAs($qa)->get(route('validation.im-revisions.pdf', $revision->id))->getContent();
    expect($pdf)->toContain('RNI Member')->toContain('RNI Team')->toContain('Regulatory')->toContain('Packaging Development')->toContain('QAC Leader');
});

test('the revision page lists the Knowledge by rows the viewer may sign', function () {
    $qa = imQa('Preparer');
    $reviewer = imQa('Reviewer');
    $approver = imQa('Approver');
    $rni = User::factory()->role('Staff', 'RNI')->create(['name' => 'RNI Member']);
    $revision = imDraft($qa);

    $this->actingAs($qa)->post(route('validation.im-revisions.submit', $revision->id), [
        'reviewer_ids' => [$reviewer->id], 'approver_user_id' => $approver->id, 'signature_password' => 'password',
    ])->assertSessionHasNoErrors();

    $rniSlot = ImSignoff::query()->where('revision_id', $revision->id)->where('role_label', 'RNI Team')->firstOrFail();
    $revisionView = $this->actingAs($rni)->get(route('validation.im-revisions.show', $revision->id));

    // Only QA sees a non-effective IM, so an RNI member gets no page, but the preparer sees none to sign.
    $revisionView->assertForbidden();
    $this->actingAs($qa)->get(route('validation.im-revisions.show', $revision->id))
        ->assertInertia(fn ($page) => $page->where('myKnowledgeIds', [])->has('reviewLabels', 4));
    expect($rniSlot->status)->toBe('Pending');
});

test('the IM numbers page links each number to its sheet or to creating one', function () {
    $qa = imQa('Preparer');
    $revision = imDraft($qa);

    // A second issued number that no master owns yet.
    $this->actingAs($qa)->post(route('validation.im.store'), ['brand_code' => 'BHM', 'product_part' => 'free number'])->assertRedirect();
    $free = ImNumber::query()->where('product_part', 'FREE NUMBER')->orWhere('number', 'like', '%FREE NUMBER%')->firstOrFail();

    $this->actingAs($qa)->get(route('validation.im.index'))->assertInertia(function ($page) use ($revision, $free) {
        $rows = collect($page->toArray()['props']['numbers']['data'])->keyBy('id');
        expect($rows[$revision->im_number_id]['sheet_revision_id'])->toBe($revision->id)
            ->and($rows[$free->id]['sheet_revision_id'])->toBeNull();
        $page->where('can.master', true);
    });

    // Opening the master list with that number preselects it in the create dialog.
    $this->actingAs($qa)->get(route('validation.im-masters.index', ['number' => $free->id]))
        ->assertInertia(fn ($page) => $page->where('prefillNumberId', $free->id)
            ->where('issuedNumbers', fn ($numbers) => collect($numbers)->pluck('id')->contains($free->id)));

    // A reader without QA rights gets neither the button nor a prefill.
    $reader = User::factory()->role('Staff', 'PRD')->create();
    $this->actingAs($reader)->get(route('validation.im.index'))->assertInertia(fn ($page) => $page->where('can.master', false));
    $this->actingAs($reader)->get(route('validation.im-masters.index', ['number' => $free->id]))->assertInertia(fn ($page) => $page->where('prefillNumberId', null));
});
