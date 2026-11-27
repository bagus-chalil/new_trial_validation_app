<?php

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('project_files');
});

function closeImprovement(array $overrides = []): Improvement
{
    $owner = User::factory()->create(['role' => 'Team Leader', 'department' => 'PROD']);

    return Improvement::create(array_merge([
        'title' => 'Cover hopper retak',
        'department' => 'PROD',
        'type' => 'Process',
        'issue' => 'Hopper cover cracks.',
        'submitted_by_user_id' => $owner->id,
        'submitted_by_name' => $owner->name,
        'status' => 'Disetujui',
        'decision' => 'To Project',
    ], $overrides));
}

function closePi(): User
{
    return User::factory()->create(['role' => 'Team Leader', 'department' => 'PI']);
}

test('PI marks an open improvement as done and the history keeps the change', function () {
    $improvement = closeImprovement();
    $pi = closePi();

    $this->actingAs($pi)->post(route('projects.improvements.close', $improvement))->assertRedirect();

    $improvement->refresh();
    expect($improvement->status)->toBe('Selesai')
        ->and($improvement->feedback_log)->toHaveCount(1)
        ->and($improvement->feedback_log[0]['status'])->toBe('Selesai')
        ->and($improvement->feedback_log[0]['by'])->toBe($pi->name);
    expect(ActivityLog::where('module', 'IMPROVEMENT')->where('action', 'CLOSE')->count())->toBe(1);

    // Already final: a second close is refused and nothing is appended.
    $this->actingAs($pi)->post(route('projects.improvements.close', $improvement))->assertSessionHasErrors('close');
    expect($improvement->refresh()->feedback_log)->toHaveCount(1);
});

test('PI rejects with a mandatory reason and a linked project stays untouched', function () {
    $improvement = closeImprovement(['status' => 'Review PI', 'decision' => 'Pending']);
    $pi = closePi();

    $this->actingAs($pi)->post(route('projects.improvements.reject', $improvement), ['reason' => ' '])
        ->assertSessionHasErrors('reason');
    expect($improvement->refresh()->status)->toBe('Review PI');

    $this->actingAs($pi)->post(route('projects.improvements.reject', $improvement), ['reason' => 'Not feasible on this line'])
        ->assertRedirect();

    $improvement->refresh();
    expect($improvement->status)->toBe('Ditolak')
        ->and($improvement->pi_reason)->toBe('Not feasible on this line')
        ->and($improvement->feedback_log[0]['text'])->toBe('Not feasible on this line');
    expect(ActivityLog::where('module', 'IMPROVEMENT')->where('action', 'REJECT')->count())->toBe(1);

    $this->actingAs($pi)->post(route('projects.improvements.reject', $improvement), ['reason' => 'again'])
        ->assertSessionHasErrors('reason');
});

test('the submitter and a viewer cannot close or reject, and can.close follows the status', function () {
    $improvement = closeImprovement();
    $owner = User::find($improvement->submitted_by_user_id);

    $this->actingAs($owner)->post(route('projects.improvements.close', $improvement))->assertForbidden();
    $this->actingAs($owner)->post(route('projects.improvements.reject', $improvement), ['reason' => 'x'])->assertForbidden();
    expect($improvement->refresh()->status)->toBe('Disetujui');

    $pi = closePi();
    $this->actingAs($pi)->get(route('projects.improvements.show', $improvement))
        ->assertInertia(fn ($page) => $page->where('can.close', true));

    $improvement->update(['status' => 'Tidak Perlu Improvement']);
    $this->actingAs($pi)->get(route('projects.improvements.show', $improvement))
        ->assertInertia(fn ($page) => $page->where('can.close', false));
});

test('the Improvement form (FR.IMP.001.00) downloads as PDF and Word for anyone who may view it', function () {
    $improvement = closeImprovement([
        'pi_feedback' => 'Cek possibility',
        'after_result' => 'Normal tanpa kendala',
        'status' => 'Selesai',
    ]);
    ImprovementFile::create([
        'improvement_id' => $improvement->id,
        'file_name' => 'before.jpeg',
        'stored_name' => 'x',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 10,
        'phase' => 'before',
        'description' => 'Foto kondisi awal',
    ]);
    $owner = User::find($improvement->submitted_by_user_id);

    $this->actingAs($owner)->get(route('projects.improvements.pdf', $improvement))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $word = $this->actingAs($owner)->get(route('projects.improvements.word', $improvement))->assertOk();
    expect($word->headers->get('content-type'))->toContain('application/msword')
        ->and($word->getContent())->toContain('FR.IMP.001.00')
        ->and($word->getContent())->toContain('Cover hopper retak')
        ->and($word->getContent())->toContain('Foto kondisi awal')
        ->and($word->getContent())->toContain('Normal tanpa kendala');

    $stranger = User::factory()->create(['role' => 'Team Leader', 'department' => 'QAC']);
    $this->actingAs($stranger)->get(route('projects.improvements.word', $improvement))->assertForbidden();
});

test('imported improvements with legacy link objects render without breaking the detail page', function () {
    $improvement = closeImprovement([
        'links' => [
            ['id' => 'abc', 'url' => 'https://example.test/sheet?e=1', 'title' => 'Sheet', 'description' => ''],
            ['id' => 'def', 'url' => 'javascript:alert(1)', 'title' => 'Bad'],
            'https://example.test/plain',
        ],
    ]);

    $this->actingAs(closePi())->get(route('projects.improvements.show', $improvement))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('improvement.links', ['https://example.test/sheet?e=1', 'https://example.test/plain']));
});
