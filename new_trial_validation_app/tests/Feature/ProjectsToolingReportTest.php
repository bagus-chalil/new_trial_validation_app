<?php

use App\Models\Project;
use App\Models\Tooling;
use App\Models\User;
use App\Support\ToolingOptions;

function reportUser(): User
{
    return User::factory()->create(['role' => 'Staff', 'department' => 'PRNI']);
}

function reportTooling(array $attrs): Tooling
{
    return Tooling::create(array_merge([
        'npd_no' => '1100/NPD',
        'product_name' => 'Cream Jar',
        'packaging_type' => 'Jar',
        'status' => ToolingOptions::STATUS_APPROVED,
        'need_invest' => 'NO',
    ], $attrs));
}

test('the report lists only finished documents and filters by invest / project / rejected', function () {
    $project = Project::create(['code' => 'PRJ-900', 'name' => 'Tooling invest', 'category' => 'Improvement', 'status' => 'On Track', 'progress' => 40]);

    reportTooling(['npd_no' => 'A-NOINV', 'need_invest' => 'NO']);
    reportTooling(['npd_no' => 'B-NEED', 'need_invest' => 'YES']);
    reportTooling(['npd_no' => 'C-PROJ', 'need_invest' => 'YES', 'project_id' => $project->id]);
    reportTooling(['npd_no' => 'D-REJ', 'status' => ToolingOptions::STATUS_REJECTED]);
    reportTooling(['npd_no' => 'E-QUEUE', 'status' => ToolingOptions::STATUS_PIE_CHECK]);

    $npds = fn ($response) => collect($response->inertiaProps('items'))->pluck('npd_no')->sort()->values()->all();

    $user = reportUser();

    $all = $this->actingAs($user)->get(route('projects.toolings.report'))->assertOk();
    expect($npds($all))->toBe(['A-NOINV', 'B-NEED', 'C-PROJ', 'D-REJ'])
        ->and($all->inertiaProps('kpi.queue'))->toBe(1)
        ->and($all->inertiaProps('counts'))->toBe(['all' => 4, 'need_invest' => 1, 'no_invest' => 1, 'project' => 1, 'rejected' => 1]);

    expect($npds($this->actingAs($user)->get(route('projects.toolings.report', ['filter' => 'need_invest']))))->toBe(['B-NEED'])
        ->and($npds($this->actingAs($user)->get(route('projects.toolings.report', ['filter' => 'no_invest']))))->toBe(['A-NOINV'])
        ->and($npds($this->actingAs($user)->get(route('projects.toolings.report', ['filter' => 'project']))))->toBe(['C-PROJ'])
        ->and($npds($this->actingAs($user)->get(route('projects.toolings.report', ['filter' => 'rejected']))))->toBe(['D-REJ'])
        ->and($npds($this->actingAs($user)->get(route('projects.toolings.report', ['q' => 'c-proj']))))->toBe(['C-PROJ']);
});

test('the CSV export, PDF and Word forms are available to every signed-in user', function () {
    $tooling = reportTooling(['npd_no' => '1200/NPD/PKD/11/2026', 'product_name' => 'Micellar Refill']);
    $user = reportUser();

    $csv = $this->actingAs($user)->get(route('projects.toolings.report.export'))->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('Micellar Refill');

    $this->actingAs($user)->get(route('projects.toolings.pdf', $tooling))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $word = $this->actingAs($user)->get(route('projects.toolings.word', $tooling))->assertOk();
    expect($word->headers->get('content-type'))->toContain('application/msword')
        ->and($word->getContent())->toContain('Packaging Proposal to Production Check')
        ->and($word->getContent())->toContain('Micellar Refill');

});

test('only an admin gets the delete button on the report and deleting returns to the report', function () {
    $tooling = reportTooling(['npd_no' => 'DEL-1']);

    $staff = $this->actingAs(reportUser())->get(route('projects.toolings.report'))->assertOk();
    expect($staff->inertiaProps('items.0.can_delete'))->toBeFalse();

    $admin = User::factory()->create(['role' => 'Admin']);
    $page = $this->actingAs($admin)->get(route('projects.toolings.report'))->assertOk();
    expect($page->inertiaProps('items.0.can_delete'))->toBeTrue();

    $this->actingAs($admin)->delete(route('projects.toolings.destroy', $tooling), ['from' => 'report'])
        ->assertRedirect(route('projects.toolings.report'));

    expect(Tooling::whereNull('deleted_at')->where('id', $tooling->id)->exists())->toBeFalse();
});
