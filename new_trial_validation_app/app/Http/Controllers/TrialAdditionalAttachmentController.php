<?php

namespace App\Http\Controllers;

use App\Actions\Trials\DeleteTrialAdditionalAttachment;
use App\Actions\Trials\SaveTrialAdditionalAttachments;
use App\Http\Requests\Trials\StoreTrialAdditionalAttachmentsRequest;
use App\Models\Trial;
use App\Models\TrialAdditionalAttachment;
use App\Policies\TrialPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Additional Attachments on the per-trial Report Summary page — supplementary
 * PDF/images the drafter or a reviewer can add at any trial status (see
 * TrialPolicy::uploadAdditionalAttachment()). No page of its own: store/
 * destroy redirect back to trials.report.show, show() streams the file.
 */
class TrialAdditionalAttachmentController extends Controller
{
    public function store(StoreTrialAdditionalAttachmentsRequest $request, int $trial, SaveTrialAdditionalAttachments $action, TrialPolicy $policy): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);
        $user = $request->user();

        $saved = $action(
            $trial,
            (array) $request->file('files', []),
            $request->filled('description') ? (string) $request->string('description') : null,
            $user,
            (string) $policy->additionalAttachmentUploaderRole($user, $trial),
        );

        Inertia::flash('toast', $saved > 0
            ? ['type' => 'success', 'message' => __('messages.toast.additional_uploaded', ['count' => $saved])]
            : ['type' => 'error', 'message' => __('messages.toast.additional_none_uploaded')]);

        return to_route('trials.report.show', $trial);
    }

    public function destroy(Request $request, int $trial, int $attachment, DeleteTrialAdditionalAttachment $action): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);
        $file = TrialAdditionalAttachment::where('trial_id', $trial->id)->findOrFail($attachment);

        Gate::authorize('deleteAdditionalAttachment', [$trial, $file]);

        $action($file, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.toast.additional_deleted')]);

        return to_route('trials.report.show', $trial);
    }

    public function show(Request $request, int $trial, int $attachment): StreamedResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        Gate::authorize('view', $trial);

        $file = TrialAdditionalAttachment::where('trial_id', $trial->id)->findOrFail($attachment);
        $disk = Storage::disk('local');

        abort_unless($disk->exists($file->storagePath()), 404);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return $disk->response($file->storagePath(), $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}
