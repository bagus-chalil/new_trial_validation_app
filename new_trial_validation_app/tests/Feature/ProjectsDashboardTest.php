<?php

use App\Models\Improvement;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectStage;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-27 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function dashViewer(): User
{
    return User::factory()->create(['role' => 'Viewer']);
}

function dashProject(array $attrs = []): Project
{
    return Project::create(array_merge([
        'name' => 'P',
        'status' => 'On Progress',
        'category' => 'Machine',
        'pic_name' => 'Zahra',
        'start_date' => '2026-11-01',
        'end_date' => '2026-12-31',
        'progress' => 30,
    ], $attrs));
}

test('the dashboard derives the effective status like the old app and counts the KPIs', function () {
    dashProject(['name' => 'Running']);
    dashProject(['name' => 'Overdue', 'end_date' => '2026-11-10']);          // past end -> Delayed
    dashProject(['name' => 'Held', 'status' => 'On Hold', 'end_date' => '2026-11-10']);
    dashProject(['name' => 'Done', 'status' => 'Completed', 'progress' => 100]);
    dashProject(['name' => 'Full', 'progress' => 100]);                       // 100% counts as Completed
    dashProject(['name' => 'Gone', 'deleted_at' => now()]);

    $this->actingAs(dashViewer())->get(route('projects.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/dashboard')
            ->where('kpi.total', 5)
            ->where('kpi.on_progress', 1)
            ->where('kpi.delayed', 1)
            ->where('kpi.on_hold', 1)
            ->where('kpi.completed', 2)
            ->where('categoryDonut.0.label', 'Machine')
            ->where('categoryDonut.0.value', 5)
            ->has('board', 5)
            ->where('board.0.status', 'On Progress'));
});

test('the period filter keeps only projects whose schedule overlaps the range', function () {
    $spring = dashProject(['name' => 'Spring', 'start_date' => '2026-03-01', 'end_date' => '2026-04-30', 'status' => 'Completed', 'progress' => 100]);
    ProjectStage::create(['project_id' => $spring->id, 'name' => 'Run', 'status' => 'Completed', 'plan_start' => '2026-03-01', 'plan_end' => '2026-04-20', 'actual_end' => '2026-04-20', 'progress' => 100]);
    dashProject(['name' => 'Now', 'start_date' => '2026-11-01', 'end_date' => '2026-12-15']);
    dashProject(['name' => 'Later', 'start_date' => '2027-02-01', 'end_date' => '2027-03-01', 'status' => 'Not Started', 'progress' => 0]);
    dashProject(['name' => 'NoDates', 'start_date' => null, 'end_date' => null]);

    $viewer = dashViewer();

    $this->actingAs($viewer)->get(route('projects.dashboard'))
        ->assertInertia(fn ($page) => $page->where('kpi.total', 4)->where('range.unlisted', 0));

    $this->actingAs($viewer)->get(route('projects.dashboard', ['period' => 'month']))
        ->assertInertia(fn ($page) => $page->where('kpi.total', 1)->where('board.0.name', 'Now')->where('range.unlisted', 1));

    $this->actingAs($viewer)->get(route('projects.dashboard', ['period' => 'custom', 'start' => '2026-03-15', 'end' => '2026-04-01']))
        ->assertInertia(fn ($page) => $page->where('kpi.total', 1)->where('board.0.name', 'Spring')->where('range.valid', true));

    // Start after end (or a missing date) is invalid and shows nothing.
    $this->actingAs($viewer)->get(route('projects.dashboard', ['period' => 'custom', 'start' => '2026-05-01', 'end' => '2026-04-01']))
        ->assertInertia(fn ($page) => $page->where('range.valid', false)->where('kpi.total', 0));
    $this->actingAs($viewer)->get(route('projects.dashboard', ['period' => 'custom', 'start' => '2026-05-01']))
        ->assertInertia(fn ($page) => $page->where('range.valid', false)->where('kpi.total', 0));

    // Unknown periods fall back to "all".
    $this->actingAs($viewer)->get(route('projects.dashboard', ['period' => 'bogus']))
        ->assertInertia(fn ($page) => $page->where('range.period', 'all')->where('kpi.total', 4));
});

test('quotation and PO cards are finance data and only sent to Admin or PI', function () {
    $project = dashProject(['name' => 'Buying']);
    ProjectQuotation::create(['project_id' => $project->id, 'vendor_name' => 'V', 'status' => 'Terpilih']);
    dashProject(['name' => 'NoQuote']);

    $this->actingAs(dashViewer())->get(route('projects.dashboard'))
        ->assertInertia(fn ($page) => $page->where('can.finance', false)->where('cards.quotation', null)->where('cards.po', null));

    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin)->get(route('projects.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('can.finance', true)
            ->where('cards.quotation.count', 1)
            ->where('cards.quotation.waiting', 1)   // NoQuote
            ->where('cards.po.count', 0)
            ->where('cards.po.waiting', 1));        // Buying: selected quotation, no PO

    PurchaseOrder::create(['project_id' => $project->id, 'po_no' => '001/PO/X', 'vendor_name' => 'V']);
    $this->actingAs($admin)->get(route('projects.dashboard'))
        ->assertInertia(fn ($page) => $page->where('cards.po.count', 1)->where('cards.po.waiting', 0));
});

test('improvement card counts and averages progress from the visible improvements', function () {
    $pi = User::factory()->create(['role' => 'Team Leader', 'department' => 'PI']);
    Improvement::create(['title' => 'New', 'status' => 'Baru', 'decision' => 'Pending']);          // 25
    Improvement::create(['title' => 'Done', 'status' => 'Selesai', 'decision' => 'To Project']);   // 100

    $this->actingAs($pi)->get(route('projects.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('cards.improvement.count', 2)
            ->where('cards.improvement.new', 1)
            ->where('cards.improvement.avg_progress', 63));

    // A plain contributor only sees their own.
    $other = User::factory()->create(['role' => 'Team Leader', 'department' => 'PROD']);
    $this->actingAs($other)->get(route('projects.dashboard'))
        ->assertInertia(fn ($page) => $page->where('cards.improvement.count', 0));
});

test('reminders cover project target, stage end and open milestones inside the window and include overdue', function () {
    $project = dashProject(['name' => 'Reminded', 'end_date' => '2026-11-29']);
    ProjectStage::create(['project_id' => $project->id, 'name' => 'Design', 'status' => 'On Progress', 'plan_start' => '2026-11-01', 'plan_end' => '2026-11-25', 'progress' => 20]);
    ProjectStage::create(['project_id' => $project->id, 'name' => 'Built', 'status' => 'Completed', 'plan_start' => '2026-11-01', 'plan_end' => '2026-11-26', 'progress' => 100]);
    $project->milestones()->create(['name' => 'FAT', 'due_date' => '2026-11-28', 'status' => 'Pending']);
    $project->milestones()->create(['name' => 'SAT', 'due_date' => '2026-11-28', 'status' => 'Done']);
    $project->milestones()->create(['name' => 'Far', 'due_date' => '2026-12-20', 'status' => 'Pending']);

    $viewer = dashViewer();

    $this->actingAs($viewer)->get(route('projects.dashboard', ['reminder' => 'week']))
        ->assertInertia(fn ($page) => $page
            ->where('reminderPeriod', 'week')
            ->has('reminders', 3)
            ->where('reminders.0.kind', 'stage')       // 2026-11-25, 2 days overdue
            ->where('reminders.0.label', 'Design')
            ->where('reminders.0.days', -2)
            ->where('reminders.1.kind', 'milestone')
            ->where('reminders.1.label', 'FAT')
            ->where('reminders.2.kind', 'project')
            ->where('reminders.2.days', 2));

    // "Today" only keeps what is already due (the overdue stage).
    $this->actingAs($viewer)->get(route('projects.dashboard', ['reminder' => 'day']))
        ->assertInertia(fn ($page) => $page->has('reminders', 1)->where('reminders.0.label', 'Design'));

    // "Month" reaches the end of November only.
    $this->actingAs($viewer)->get(route('projects.dashboard', ['reminder' => 'month']))
        ->assertInertia(fn ($page) => $page->has('reminders', 3));
});

test('attention flags late, at-risk, deadline and late stages and skips finished projects', function () {
    dashProject(['name' => 'Late', 'end_date' => '2026-11-20', 'progress' => 40]);
    dashProject(['name' => 'Risky', 'start_date' => '2026-11-01', 'end_date' => '2026-12-11', 'progress' => 5]);   // ~65% planned vs 5%
    dashProject(['name' => 'Soon', 'start_date' => '2026-11-10', 'end_date' => '2026-12-05', 'progress' => 90]);
    dashProject(['name' => 'Finished', 'status' => 'Completed', 'progress' => 100, 'end_date' => '2026-11-01']);

    $tags = fn () => collect($this->actingAs(dashViewer())->get(route('projects.dashboard'))
        ->viewData('page')['props']['attention'])->mapWithKeys(fn ($a) => [$a['project']['name'].'|'.$a['tag'] => true])->keys()->all();

    expect($tags())->toContain('Late|late', 'Risky|at_risk', 'Soon|deadline')
        ->and(collect($tags())->contains(fn ($k) => str_starts_with($k, 'Finished|')))->toBeFalse();
});

test('the dashboard lang group has id en ko parity and the tab is translated', function () {
    $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
        $out = [];
        foreach ($a as $k => $v) {
            is_array($v) ? $out += $flatten($v, $prefix.$k.'.') : $out[$prefix.$k] = true;
        }

        return $out;
    };

    $id = array_keys($flatten(trans('projects.dashboard', [], 'id')));
    expect($id)->not->toBeEmpty()
        ->and(array_keys($flatten(trans('projects.dashboard', [], 'en'))))->toBe($id)
        ->and(array_keys($flatten(trans('projects.dashboard', [], 'ko'))))->toBe($id);
});
