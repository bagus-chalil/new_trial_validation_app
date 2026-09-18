<?php

use App\Mail\TrialReviewRequestedMail;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use App\Models\ValidationParameter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

function makeReviewableTrial(array $attributes = []): Trial
{
    return Trial::create([
        'trial_code' => $attributes['trial_code'] ?? 'TRIAL-REVIEW-1',
        'product_name' => 'Sample Product',
        'product_type' => $attributes['product_type'] ?? 'Tube',
        'progress_status' => $attributes['progress_status'] ?? 'Draft',
        'current_step' => $attributes['current_step'] ?? 'Attachment',
        'created_by' => $attributes['created_by'] ?? 'owner@local.test',
        'batch_number' => 'B1',
        'bulk_code' => 'BC1',
        'support_team' => 'QA',
        'initiated_person_team' => 'Someone',
        'reason' => 'Testing',
        'bom' => 'BOM text',
        'revision_no' => $attributes['revision_no'] ?? 0,
        'approver_user_id' => $attributes['approver_user_id'] ?? null,
    ]);
}

function makeCompleteTrial(array $attributes = []): Trial
{
    $trial = makeReviewableTrial($attributes);
    $param = ValidationParameter::create([
        'product_type' => $trial->product_type,
        'parameter_name' => 'Weight',
        'specification' => 'Spec',
        'sort_order' => 1,
    ]);

    DB::table('trials_results')->insert([
        'trial_id' => $trial->id,
        'parameter_id' => $param->id,
        'result_value' => 'Conform',
        'decision' => 'OK',
        'remark' => '',
        'updated_at' => Carbon::now(),
    ]);

    return $trial;
}

test('the review page shows completeness errors when validation is incomplete', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeReviewableTrial(['created_by' => $owner->email]);
    ValidationParameter::create([
        'product_type' => $trial->product_type,
        'parameter_name' => 'Weight',
        'specification' => 'Spec',
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($owner)->get(route('trials.review.edit', $trial));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('canEdit', true)
        ->where('completeness.0', 'Parameter Weight belum memiliki decision.'));
});

test('the review page shows no completeness errors when validation is complete', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $response = $this->actingAs($owner)->get(route('trials.review.edit', $trial));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('completeness', []));
});

test('the review page only offers approver-eligible roles as approvers', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $eligible = User::factory()->create(['name' => 'Eligible Manager', 'role' => 'Manager QAC']);
    $ineligible = User::factory()->create(['name' => 'Ineligible Staff', 'role' => 'Staff']);
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $response = $this->actingAs($owner)->get(route('trials.review.edit', $trial));

    $response->assertOk();
    $response->assertInertia(function ($page) use ($eligible, $ineligible) {
        $ids = collect($page->toArray()['props']['approvers'])->pluck('id');

        expect($ids)->toContain($eligible->id);
        expect($ids)->not->toContain($ineligible->id);
    });
});

test('a soft-deleted trial 404s on the review page', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeCompleteTrial(['created_by' => $owner->email]);
    $trial->deleted_at = Carbon::now();
    $trial->save();

    $this->actingAs($owner)->get(route('trials.review.edit', $trial))->assertNotFound();
});

test('an in-review trial cannot re-submit for review even before any department has reviewed', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $approver = User::factory()->create(['role' => 'Manager QAC']);
    $trial = makeCompleteTrial([
        'created_by' => $owner->email,
        'progress_status' => 'In Review',
        'current_step' => 'Review',
        'approver_user_id' => $approver->id,
    ]);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'PRD', 'review_round' => 1, 'status' => 'Pending', 'is_required' => true]);

    $response = $this->actingAs($owner)->get(route('trials.review.edit', $trial));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('canEdit', false));

    $storeResponse = $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PRD'],
        'reviewer_user_ids' => [],
        'approver_user_id' => $approver->id,
    ]);

    $storeResponse->assertForbidden();
});

test('a need-revision trial can still reach and use the review-submit form', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeCompleteTrial(['created_by' => $owner->email, 'progress_status' => 'Need Revision']);

    $response = $this->actingAs($owner)->get(route('trials.review.edit', $trial));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('canEdit', true));
});

test('submitting for review creates pending trials_review rows and moves the trial to In Review', function () {
    Mail::fake();

    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $approver = User::factory()->create(['role' => 'Manager QAC']);
    $prodReviewer = User::factory()->reviewUnit('PROD')->create();
    $qacReviewer = User::factory()->reviewUnit('QAC')->create();
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $response = $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD', 'QAC'],
        'reviewer_user_ids' => ['PROD' => $prodReviewer->id, 'QAC' => $qacReviewer->id],
        'approver_user_id' => $approver->id,
    ]);

    $response->assertRedirect(route('trials.report.show', $trial));

    $reviews = TrialReview::where('trial_id', $trial->id)->orderBy('department')->get();
    expect($reviews)->toHaveCount(2);
    expect($reviews->pluck('department')->all())->toBe(['PROD', 'QAC']);
    expect($reviews->every(fn (TrialReview $r) => $r->status === 'Pending'))->toBeTrue();
    expect($reviews->firstWhere('department', 'PROD')->reviewer_user_id)->toBe($prodReviewer->id);
    expect($reviews->firstWhere('department', 'QAC')->reviewer_user_id)->toBe($qacReviewer->id);

    $trial->refresh();
    expect($trial->progress_status)->toBe('In Review');
    expect($trial->current_step)->toBe('Review');
    expect($trial->pending_with)->toBe('PROD,QAC');
    expect($trial->approver_user_id)->toBe($approver->id);

    $log = ActivityLog::where('module', 'REVIEW')->where('action', 'SUBMIT_REVIEW')->first();
    expect($log)->not->toBeNull();
    expect($log->record_id)->toBe((string) $trial->id);

    expect(Notification::where('trial_id', $trial->id)->where('type', 'review')->count())->toBe(2);
    expect(Notification::where('trial_id', $trial->id)->where('type', 'info')->count())->toBe(1);

    Mail::assertSent(TrialReviewRequestedMail::class, 2);
    Mail::assertSent(TrialReviewRequestedMail::class, fn ($mail) => $mail->hasTo($prodReviewer->email) && $mail->department === 'PROD');
    Mail::assertSent(TrialReviewRequestedMail::class, fn ($mail) => $mail->hasTo($qacReviewer->email) && $mail->department === 'QAC');
});

test('a Team Leader Production user can be picked as the approver', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $approver = User::factory()->create(['role' => 'Team Leader Production']);
    $prodReviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $response = $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD'],
        'reviewer_user_ids' => ['PROD' => $prodReviewer->id],
        'approver_user_id' => $approver->id,
    ]);

    $response->assertRedirect(route('trials.report.show', $trial));
    expect($trial->fresh()->approver_user_id)->toBe($approver->id);
});

test('submitting for review is rejected when validation is incomplete and nothing is persisted', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $approver = User::factory()->create(['role' => 'Manager QAC']);
    $trial = makeReviewableTrial(['created_by' => $owner->email]);
    ValidationParameter::create([
        'product_type' => $trial->product_type,
        'parameter_name' => 'Weight',
        'specification' => 'Spec',
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD'],
        'approver_user_id' => $approver->id,
    ]);

    $response->assertSessionHasErrors('completeness');
    expect(TrialReview::where('trial_id', $trial->id)->count())->toBe(0);
    expect($trial->fresh()->progress_status)->toBe('Draft');
});

test('submitting for review with an unknown department is rejected', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $approver = User::factory()->create(['role' => 'Manager QAC']);
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $response = $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['BOGUS'],
        'approver_user_id' => $approver->id,
    ]);

    $response->assertSessionHasErrors('departments.0');
    expect(TrialReview::where('trial_id', $trial->id)->count())->toBe(0);
});

test('submitting for review with an inactive approver is rejected', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $approver = User::factory()->create(['role' => 'Manager QAC', 'is_active' => false]);
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $response = $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD'],
        'approver_user_id' => $approver->id,
    ]);

    $response->assertSessionHasErrors('approver_user_id');
});

test('submitting for review with a non-approver-role user as approver is rejected', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $staffApprover = User::factory()->create(['role' => 'Staff']);
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $response = $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD'],
        'approver_user_id' => $staffApprover->id,
    ]);

    $response->assertSessionHasErrors('approver_user_id');
    expect(TrialReview::where('trial_id', $trial->id)->count())->toBe(0);
    expect($trial->fresh()->progress_status)->toBe('Draft');
});

test('a full revision cycle allows a second round of review after Need Revision and resubmit', function () {
    // Regression test for the KRITIS SIT finding (2026-09-18): final_decision
    // was never reset to null on resubmit, so TrialReviewPolicy::update()
    // (which requires final_decision === null) permanently 403'd every
    // reviewer on round 2 onward, stranding the trial in "In Review" forever.
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $manager = User::factory()->role('Manager QAC')->create();
    $prodReviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD'],
        'reviewer_user_ids' => ['PROD' => $prodReviewer->id],
        'approver_user_id' => $manager->id,
    ])->assertRedirect(route('trials.report.show', $trial));

    $round1Review = TrialReview::where('trial_id', $trial->id)->where('review_round', 1)->firstOrFail();
    $this->actingAs($prodReviewer)->put(route('reviews.update', $round1Review), [
        'comment' => 'Round 1 looks fine',
    ])->assertRedirect(route('reviews.index'));

    expect($trial->fresh()->progress_status)->toBe('Ready for Approval');

    $this->actingAs($manager)->post(route('approvals.update', $trial), [
        'decision' => 'Need Revision',
        'approval_comment' => 'Please fix the data',
        'signature_password' => 'password',
    ])->assertRedirect(route('approvals.index'));

    $trial->refresh();
    expect($trial->progress_status)->toBe('Need Revision');
    expect($trial->final_decision)->toBe('Need Revision');

    $this->actingAs($owner)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD'],
        'reviewer_user_ids' => ['PROD' => $prodReviewer->id],
        'approver_user_id' => $manager->id,
    ])->assertRedirect(route('trials.report.show', $trial));

    $trial->refresh();
    expect($trial->progress_status)->toBe('In Review');
    expect($trial->final_decision)->toBeNull();
    expect($trial->revision_no)->toBe(1);

    $round2Review = TrialReview::where('trial_id', $trial->id)->where('review_round', 2)->firstOrFail();

    $this->actingAs($prodReviewer)->put(route('reviews.update', $round2Review), [
        'comment' => 'Round 2 looks fine now',
    ])->assertRedirect(route('reviews.index'));

    $round2Review->refresh();
    expect($round2Review->status)->toBe('Reviewed');
    expect($trial->fresh()->progress_status)->toBe('Ready for Approval');
});

test('a staff member without edit rights is forbidden from submitting for review', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $otherStaff = User::factory()->create(['email' => 'other@local.test']);
    $approver = User::factory()->create(['role' => 'Manager QAC']);
    $trial = makeCompleteTrial(['created_by' => $owner->email]);

    $this->actingAs($otherStaff)->post(route('trials.review.store', $trial), [
        'departments' => ['PROD'],
        'approver_user_id' => $approver->id,
    ])->assertForbidden();
});
