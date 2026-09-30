<?php

namespace App\Actions\Trials;

use App\Models\ActivityLog;
use App\Models\TrialAdditionalAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DeleteTrialAdditionalAttachment
{
    public function __invoke(TrialAdditionalAttachment $attachment, User $user): void
    {
        $snapshot = $attachment->only(['id', 'trial_id', 'original_name', 'file_name', 'mime_type', 'uploaded_by_name', 'uploader_role']);
        $path = $attachment->storagePath();

        $attachment->delete();

        Storage::disk('local')->delete($path);

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'DELETE',
            'module' => 'ADDITIONAL_ATTACHMENT',
            'record_id' => (string) $snapshot['id'],
            'record_label' => $snapshot['original_name'],
            'old_data' => json_encode($snapshot),
            'new_data' => null,
        ]);
    }
}
