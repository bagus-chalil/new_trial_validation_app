<?php

use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialAdditionalAttachment;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeAdditionalAttachmentTrial(array $attributes = []): Trial
{
    return Trial::create([
        'trial_code' => $attributes['trial_code'] ?? 'TRIAL-ADD-1',
        'product_name' => 'Sample Product',
        'product_type' => 'Tube',
        'progress_status' => $attributes['progress_status'] ?? 'Approved',
        'current_step' => 'Approval',
        'created_by' => $attributes['created_by'] ?? 'owner@local.test',
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

test('the drafter can upload a pdf and an image on an approved trial', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeAdditionalAttachmentTrial();

    $response = $this->actingAs($owner)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [
            UploadedFile::fake()->create('coa.pdf', 200, 'application/pdf'),
            UploadedFile::fake()->image('extra.png'),
        ],
        'description' => 'COA supplier',
    ]);

    $response->assertRedirect(route('trials.report.show', $trial));

    $rows = TrialAdditionalAttachment::where('trial_id', $trial->id)->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('uploader_role')->unique()->all())->toBe(['Drafter']);
    expect($rows->firstWhere('original_name', 'coa.pdf')->mime_type)->toBe('application/pdf');

    foreach ($rows as $row) {
        Storage::disk('local')->assertExists($row->storagePath());
    }

    expect($trial->fresh()->progress_status)->toBe('Approved');
    expect(ActivityLog::where('module', 'ADDITIONAL_ATTACHMENT')->where('action', 'CREATE')->exists())->toBeTrue();
});

test('an assigned reviewer can upload regardless of trial status', function () {
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeAdditionalAttachmentTrial(['progress_status' => 'Rejected']);
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'PROD', 'review_round' => 1, 'status' => 'Reviewed', 'reviewer_user_id' => $reviewer->id]);

    $this->actingAs($reviewer)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->image('photo.jpg')],
    ])->assertRedirect();

    expect(TrialAdditionalAttachment::where('trial_id', $trial->id)->value('uploader_role'))->toBe('Reviewer (PROD)');
});

test('a user who is neither drafter nor reviewer cannot upload', function () {
    $staff = User::factory()->role('Staff')->create();
    $trial = makeAdditionalAttachmentTrial();

    $this->actingAs($staff)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->image('photo.jpg')],
    ])->assertForbidden();

    expect(TrialAdditionalAttachment::count())->toBe(0);
});

test('non pdf or image files are rejected', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeAdditionalAttachmentTrial();

    $this->actingAs($owner)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->create('notes.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
    ])->assertSessionHasErrors('files.0');

    expect(TrialAdditionalAttachment::count())->toBe(0);
});

test('the per-trial cap of 10 counts files already stored', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeAdditionalAttachmentTrial();

    foreach (range(1, 9) as $i) {
        TrialAdditionalAttachment::create([
            'trial_id' => $trial->id,
            'original_name' => "f{$i}.pdf",
            'file_name' => "f{$i}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_by_user_id' => $owner->id,
        ]);
    }

    $this->actingAs($owner)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ])->assertSessionHasErrors('files');

    expect(TrialAdditionalAttachment::count())->toBe(9);

    $this->actingAs($owner)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertSessionHasNoErrors();

    expect(TrialAdditionalAttachment::count())->toBe(10);
});

test('only the uploader can delete their own attachment', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $reviewer = User::factory()->reviewUnit('PROD')->create();
    $trial = makeAdditionalAttachmentTrial();
    TrialReview::create(['trial_id' => $trial->id, 'department' => 'PROD', 'review_round' => 1, 'status' => 'Reviewed', 'reviewer_user_id' => $reviewer->id]);

    $this->actingAs($owner)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->image('photo.jpg')],
    ]);
    $attachment = TrialAdditionalAttachment::firstOrFail();

    $this->actingAs($reviewer)
        ->delete(route('trials.additional-attachments.destroy', [$trial, $attachment]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('trials.additional-attachments.destroy', [$trial, $attachment]))
        ->assertRedirect(route('trials.report.show', $trial));

    expect(TrialAdditionalAttachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->storagePath());
});

test('the report page exposes additional attachments and upload permission', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $viewer = User::factory()->role('Viewer')->create();
    $trial = makeAdditionalAttachmentTrial();

    $this->actingAs($owner)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->create('coa.pdf', 50, 'application/pdf')],
    ]);

    $this->actingAs($owner)->get(route('trials.report.show', $trial))
        ->assertInertia(fn ($page) => $page
            ->where('canUploadAdditionalAttachment', true)
            ->where('additionalAttachmentLimit', 10)
            ->has('additionalAttachments', 1)
            ->where('additionalAttachments.0.is_pdf', true)
            ->where('additionalAttachments.0.can_delete', true));

    $this->actingAs($viewer)->get(route('trials.report.show', $trial))
        ->assertInertia(fn ($page) => $page
            ->where('canUploadAdditionalAttachment', false)
            ->where('additionalAttachments.0.can_delete', false));

    $attachment = TrialAdditionalAttachment::firstOrFail();
    $this->actingAs($viewer)
        ->get(route('trials.additional-attachments.show', [$trial, $attachment]))
        ->assertOk();
});

test('an admin can upload on any trial and delete anyone\'s attachment', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $admin = User::factory()->role('Admin')->create();
    $trial = makeAdditionalAttachmentTrial();

    $this->actingAs($owner)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->image('owner.jpg')],
    ]);
    $this->actingAs($admin)->post(route('trials.additional-attachments.store', $trial), [
        'files' => [UploadedFile::fake()->image('admin.jpg')],
    ])->assertSessionHasNoErrors();

    expect(TrialAdditionalAttachment::where('original_name', 'admin.jpg')->value('uploader_role'))->toBe('Admin');

    $ownerFile = TrialAdditionalAttachment::where('original_name', 'owner.jpg')->firstOrFail();
    $this->actingAs($admin)
        ->delete(route('trials.additional-attachments.destroy', [$trial, $ownerFile]))
        ->assertRedirect(route('trials.report.show', $trial));

    expect(TrialAdditionalAttachment::pluck('original_name')->all())->toBe(['admin.jpg']);
});
