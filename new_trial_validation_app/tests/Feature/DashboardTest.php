<?php

use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Support\Carbon;

function makeDashboardTrial(array $attributes = []): Trial
{
    return Trial::create(array_merge([
        'trial_code' => 'TRIAL-'.uniqid(),
        'product_name' => 'Sample Product',
        'finish_good_code' => 'FG-001',
        'product_type' => 'Tube',
        'progress_status' => 'Approved',
        'revision_no' => 0,
        'created_by' => 'owner@local.test',
    ], $attributes));
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard summary counts match the visible trials', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeDashboardTrial(['trial_code' => 'TRIAL-DRAFT', 'progress_status' => 'Draft']);
    makeDashboardTrial(['trial_code' => 'TRIAL-APPROVED-1', 'progress_status' => 'Approved']);
    makeDashboardTrial(['trial_code' => 'TRIAL-APPROVED-2', 'progress_status' => 'Approved']);
    makeDashboardTrial(['trial_code' => 'TRIAL-REJECTED', 'progress_status' => 'Rejected']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('summary.total', 4)
        ->where('summary.draft', 1)
        ->where('summary.approved', 2)
        ->where('summary.rejected', 1));
});

test('the q filter searches trial code and product name', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeDashboardTrial(['trial_code' => 'TRIAL-MATCH', 'product_name' => 'Other Product']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OTHER', 'product_name' => 'Different Product']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard', ['q' => 'MATCH']));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-MATCH')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-OTHER')));
});

test('the status filter narrows the trial list', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeDashboardTrial(['trial_code' => 'TRIAL-DRAFT-1', 'progress_status' => 'Draft']);
    makeDashboardTrial(['trial_code' => 'TRIAL-APPROVED-1', 'progress_status' => 'Approved']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard', ['status' => 'Draft']));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-DRAFT-1')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-APPROVED-1')));
});

test('a non-super-admin does not see another user\'s draft trial', function () {
    $staff = User::factory()->create(['role' => 'Staff', 'email' => 'staff@local.test']);
    makeDashboardTrial(['trial_code' => 'TRIAL-SOMEONE-ELSE-DRAFT', 'progress_status' => 'Draft', 'created_by' => 'other@local.test']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OWN-DRAFT', 'progress_status' => 'Draft', 'created_by' => 'staff@local.test']);

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('trials.data', fn ($data) => collect($data)->pluck('trial_code')->contains('TRIAL-OWN-DRAFT')
            && ! collect($data)->pluck('trial_code')->contains('TRIAL-SOMEONE-ELSE-DRAFT')));
});

test('myWork lists the current user\'s own active trials but excludes finished ones', function () {
    $staff = User::factory()->create(['email' => 'staff@local.test']);
    makeDashboardTrial(['trial_code' => 'TRIAL-MINE-DRAFT', 'progress_status' => 'Draft', 'created_by' => 'staff@local.test']);
    makeDashboardTrial(['trial_code' => 'TRIAL-MINE-APPROVED', 'progress_status' => 'Approved', 'created_by' => 'staff@local.test']);
    makeDashboardTrial(['trial_code' => 'TRIAL-SOMEONE-ELSE', 'progress_status' => 'Draft', 'created_by' => 'other@local.test']);

    $response = $this->actingAs($staff)->get(route('my-work'));

    $response->assertInertia(fn ($page) => $page
        ->where('myWork.myTrialsTotal', 1)
        ->has('myWork.myTrials', 1)
        ->where('myWork.myTrials.0.trial_code', 'TRIAL-MINE-DRAFT'));
});

test('myWork lists pending reviews for the current user\'s department only', function () {
    $reviewer = User::factory()->role('PRD')->create();
    $trial = makeDashboardTrial(['trial_code' => 'TRIAL-REVIEW-MINE', 'progress_status' => 'In Review', 'revision_no' => 0]);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'PRD', 'review_round' => 1, 'status' => 'Pending']);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Pending']);

    $response = $this->actingAs($reviewer)->get(route('my-work'));

    $response->assertInertia(fn ($page) => $page
        ->where('myWork.pendingReviewsTotal', 1)
        ->has('myWork.pendingReviews', 1)
        ->where('myWork.pendingReviews.0.trial_code', 'TRIAL-REVIEW-MINE')
        ->where('myWork.pendingReviews.0.department', 'PRD'));
});

test('myWork lists only approvals specifically assigned to the current user, not the whole approval queue', function () {
    $managerQac = User::factory()->role('Manager QAC')->create();
    makeDashboardTrial(['trial_code' => 'TRIAL-APPROVAL-MINE', 'progress_status' => 'Ready for Approval', 'approver_user_id' => $managerQac->id]);
    makeDashboardTrial(['trial_code' => 'TRIAL-APPROVAL-OTHER', 'progress_status' => 'Ready for Approval', 'approver_user_id' => null]);

    $response = $this->actingAs($managerQac)->get(route('my-work'));

    $response->assertInertia(fn ($page) => $page
        ->where('myWork.pendingApprovalsTotal', 1)
        ->has('myWork.pendingApprovals', 1)
        ->where('myWork.pendingApprovals.0.trial_code', 'TRIAL-APPROVAL-MINE'));
});

test('overview headline computes approval rate and average approval time from decided trials', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $approved1 = makeDashboardTrial(['trial_code' => 'TRIAL-OV-APR-1', 'progress_status' => 'Approved', 'approved_at' => Carbon::now()]);
    $approved1->created_at = Carbon::now()->subDays(2);
    $approved1->save();

    $approved2 = makeDashboardTrial(['trial_code' => 'TRIAL-OV-APR-2', 'progress_status' => 'Approved', 'approved_at' => Carbon::now()]);
    $approved2->created_at = Carbon::now()->subDays(4);
    $approved2->save();

    makeDashboardTrial(['trial_code' => 'TRIAL-OV-REJ', 'progress_status' => 'Rejected']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('overview.headline.approvalRate', 66.7)
        ->where('overview.headline.avgApprovalDays', 3));
});

test('overview headline approval rate and average approval time are null when nothing has been decided', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-DRAFT', 'progress_status' => 'Draft']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('overview.headline.approvalRate', null)
        ->where('overview.headline.avgApprovalDays', null));
});

test('overview headline sums active (in-progress) trials correctly', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-ACT-DRAFT', 'progress_status' => 'Draft']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-ACT-REVIEW', 'progress_status' => 'In Review']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-ACT-READY', 'progress_status' => 'Ready for Approval']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-ACT-REVISION', 'progress_status' => 'Need Revision']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-ACT-APPROVED', 'progress_status' => 'Approved']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('overview.headline.activeTrials', 4));
});

test('overview headline picks the reviewer department with the most pending reviews as the bottleneck', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $trialA = makeDashboardTrial(['trial_code' => 'TRIAL-OV-BN-A', 'progress_status' => 'In Review', 'revision_no' => 0]);
    $trialB = makeDashboardTrial(['trial_code' => 'TRIAL-OV-BN-B', 'progress_status' => 'In Review', 'revision_no' => 0]);
    TrialReview::create(['trial_id' => $trialA->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Pending']);
    TrialReview::create(['trial_id' => $trialB->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Pending']);
    TrialReview::create(['trial_id' => $trialA->id, 'department' => 'PRD', 'review_round' => 1, 'status' => 'Pending']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('overview.headline.bottleneckDepartment.department', 'QAC')
        ->where('overview.headline.bottleneckDepartment.count', 2));
});

test('overview headline has no bottleneck department when nothing is pending review', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-NOPEND', 'progress_status' => 'Draft']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('overview.headline.bottleneckDepartment', null));
});

test('overview trend returns every month in the range, including zero-count months, in order', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $trial = makeDashboardTrial(['trial_code' => 'TRIAL-OV-TREND']);
    $trial->created_at = Carbon::now()->startOfMonth();
    $trial->save();

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('overview.trend', function ($trend) {
        $expectedFirst = Carbon::now()->startOfMonth()->subMonths(5)->format('Y-m');
        $expectedLast = Carbon::now()->startOfMonth()->format('Y-m');

        return count($trend) === 6
            && $trend[0]['period'] === $expectedFirst
            && $trend[5]['period'] === $expectedLast
            && $trend[5]['count'] === 1;
    }));
});

test('overview buckets product types beyond the top 6 into "Lainnya"', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    foreach (range(1, 7) as $i) {
        makeDashboardTrial(['trial_code' => "TRIAL-OV-PT-{$i}", 'product_type' => "Type{$i}"]);
    }
    makeDashboardTrial(['trial_code' => 'TRIAL-OV-PT-1B', 'product_type' => 'Type1']);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('overview.productTypeBreakdown', function ($rows) {
        $labels = collect($rows)->pluck('label');

        return $labels->count() === 7 && $labels->contains('Lainnya');
    }));
});

test('overview department-pending breakdown only counts trials visible to the acting user', function () {
    $staff = User::factory()->create(['role' => 'Staff', 'email' => 'staff@local.test']);

    $hiddenTrial = makeDashboardTrial(['trial_code' => 'TRIAL-OV-HIDDEN', 'progress_status' => 'Draft', 'created_by' => 'other@local.test']);
    TrialReview::create(['trial_id' => $hiddenTrial->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Pending']);

    $visibleTrial = makeDashboardTrial(['trial_code' => 'TRIAL-OV-VISIBLE', 'progress_status' => 'In Review', 'revision_no' => 0]);
    TrialReview::create(['trial_id' => $visibleTrial->id, 'department' => 'QAC', 'review_round' => 1, 'status' => 'Pending']);

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('overview.departmentPending', function ($rows) {
        $qac = collect($rows)->firstWhere('department', 'QAC');

        return $qac['count'] === 1;
    }));
});
