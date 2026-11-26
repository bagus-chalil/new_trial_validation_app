<?php

namespace App\Actions\ImMaster;

use App\Models\ImFile;
use App\Models\ImRevision;
use App\Models\User;
use App\Support\ImLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload / delete the drawings, photos and source documents of an IM revision.
 * Partial success like the Trial attachments: each file is checked on its own
 * (real MIME, 10 MB), the valid ones are kept. Private `local` disk.
 */
class SaveImFiles
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * @param  array<int, UploadedFile|null>  $files
     * @return array{saved: int, errors: list<string>}
     */
    public function store(ImRevision $revision, string $category, array $files, ?string $caption, User $user): array
    {
        $saved = 0;
        $errors = [];

        foreach ($files as $i => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                $errors[] = __('im_master.upload.failed', ['number' => $i + 1]);

                continue;
            }

            $name = $file->getClientOriginalName();
            $ext = self::ALLOWED[(string) $file->getMimeType()] ?? null;

            if ($file->getSize() > self::MAX_BYTES) {
                $errors[] = __('im_master.upload.too_large', ['name' => $name]);
            } elseif ($ext === null || ($category !== 'source_document' && $ext === 'pdf')) {
                $errors[] = __('im_master.upload.not_allowed', ['name' => $name]);
            } else {
                $stored = bin2hex(random_bytes(16)).'.'.$ext;
                $file->storeAs('im/'.$revision->id, $stored, 'local');

                ImFile::create([
                    'revision_id' => $revision->id,
                    'category' => $category,
                    'caption' => mb_substr(trim((string) $caption), 0, 255),
                    'original_name' => mb_substr($name, 0, 255),
                    'file_name' => $stored,
                    'mime' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'uploaded_by_user_id' => $user->id,
                ]);
                $saved++;
            }
        }

        if ($saved > 0) {
            ImLog::write($user, 'UPLOAD', $revision, ['category' => $category, 'count' => $saved]);
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    public function delete(ImRevision $revision, ImFile $file, User $user): void
    {
        Storage::disk('local')->delete('im/'.$revision->id.'/'.$file->file_name);
        $file->delete();

        ImLog::write($user, 'DELETE_FILE', $revision, ['file' => $file->original_name]);
    }
}
