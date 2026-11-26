<?php

namespace App\Actions\ImMaster;

use App\Models\ImEvidence;
use App\Models\ImFile;
use App\Models\ImMaster;
use App\Models\ImNumber;
use App\Models\ImParameter;
use App\Models\ImRevision;
use App\Models\ImSignoff;
use App\Models\User;
use App\Models\ValidationCaseLink;
use App\Support\ImLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Throws away a Draft revision that was never submitted. Its IM number is
 * voided (never reused); the master goes too when it has no other revision.
 */
class DiscardImRevision
{
    public function __invoke(ImRevision $revision, User $user): void
    {
        ImLog::write($user, 'DISCARD', $revision);

        $files = ImFile::query()->where('revision_id', $revision->id)->get();

        DB::transaction(function () use ($revision) {
            if ($revision->im_number_id !== null) {
                ImNumber::query()->whereKey($revision->im_number_id)->update(['status' => 'Void', 'void_reason' => 'Draft IM dibatalkan']);
            }

            ValidationCaseLink::query()->where('kind', 'im')->where('ref_key', $revision->im_number)->delete();
            ImParameter::query()->where('revision_id', $revision->id)->delete();
            ImEvidence::query()->where('revision_id', $revision->id)->delete();
            ImSignoff::query()->where('revision_id', $revision->id)->delete();
            ImFile::query()->where('revision_id', $revision->id)->delete();
            $revision->delete();

            if (! ImRevision::query()->where('master_id', $revision->master_id)->exists()) {
                ImMaster::query()->whereKey($revision->master_id)->delete();
            }
        });

        foreach ($files as $file) {
            Storage::disk('local')->delete('im/'.$revision->id.'/'.$file->file_name);
        }
    }
}
