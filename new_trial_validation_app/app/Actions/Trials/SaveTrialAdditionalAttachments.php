<?php

namespace App\Actions\Trials;

use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialAdditionalAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores supplementary PDF/images on a trial. Type/size/per-trial-cap checks
 * already ran in StoreTrialAdditionalAttachmentsRequest; unlike the wizard
 * Step 5 upload this never touches progress_status/current_step, since these
 * files are deliberately independent of the trial's workflow position.
 */
class SaveTrialAdditionalAttachments
{
    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function __invoke(Trial $trial, array $files, ?string $description, User $user, string $uploaderRole): int
    {
        $disk = Storage::disk('local');
        $directory = TrialAdditionalAttachment::STORAGE_DIR.'/'.$trial->id;
        $saved = 0;

        foreach ($files as $file) {
            $mime = (string) $file->getMimeType();
            $extension = TrialAdditionalAttachment::ALLOWED_MIME_TO_EXTENSION[$mime] ?? null;

            if ($extension === null) {
                continue;
            }

            $name = bin2hex(random_bytes(16)).'.'.$extension;

            if ($disk->putFileAs($directory, $file, $name) === false) {
                continue;
            }

            TrialAdditionalAttachment::create([
                'trial_id' => $trial->id,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_name' => $name,
                'mime_type' => $mime,
                'size_bytes' => (int) $file->getSize(),
                'description' => $description,
                'uploaded_by_user_id' => $user->id,
                'uploaded_by_name' => $user->name ?: $user->email,
                'uploader_role' => $uploaderRole,
            ]);

            $saved++;
        }

        if ($saved > 0) {
            ActivityLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'action' => 'CREATE',
                'module' => 'ADDITIONAL_ATTACHMENT',
                'record_id' => (string) $trial->id,
                'record_label' => $trial->trial_code,
                'old_data' => null,
                'new_data' => json_encode(['count' => $saved, 'as' => $uploaderRole, 'status' => $trial->progress_status]),
            ]);
        }

        return $saved;
    }
}
