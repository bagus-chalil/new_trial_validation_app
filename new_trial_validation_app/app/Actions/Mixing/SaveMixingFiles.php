<?php

namespace App\Actions\Mixing;

use App\Models\ActivityLog;
use App\Models\Mixing;
use App\Models\MixingFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload / delete Mixing evidence. Partial success like the Trial attachments:
 * each file is checked on its own (real MIME, 10 MB), the valid ones are kept.
 * Images and PDF are allowed (a revised batch record is usually a PDF).
 */
class SaveMixingFiles
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
    public function store(Mixing $mixing, string $category, array $files, ?string $caption, User $user): array
    {
        $disk = Storage::disk('local');
        $saved = 0;
        $errors = [];

        foreach ($files as $i => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                $errors[] = __('mixing.upload.failed', ['number' => $i + 1]);

                continue;
            }

            $name = $file->getClientOriginalName();
            if ($file->getSize() > self::MAX_BYTES) {
                $errors[] = __('mixing.upload.too_large', ['name' => $name]);

                continue;
            }

            $mime = (string) $file->getMimeType();
            $ext = self::ALLOWED[$mime] ?? null;
            if ($ext === null) {
                $errors[] = __('mixing.upload.not_allowed', ['name' => $name]);

                continue;
            }

            $stored = bin2hex(random_bytes(16)).'.'.$ext;
            if ($disk->putFileAs('mixing/'.$mixing->id, $file, $stored) === false) {
                $errors[] = __('mixing.upload.failed', ['number' => $i + 1]);

                continue;
            }

            MixingFile::create([
                'mixing_id' => $mixing->id,
                'category' => $category,
                'original_name' => mb_substr($name, 0, 255),
                'file_name' => $stored,
                'mime' => $mime,
                'size_bytes' => (int) $file->getSize(),
                'caption' => $caption,
                'uploaded_by' => (string) $user->email,
            ]);
            $saved++;
        }

        if ($saved > 0) {
            $this->log($user, 'CREATE', $mixing, ['category' => $category, 'count' => $saved]);
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    public function delete(Mixing $mixing, MixingFile $file, User $user): void
    {
        Storage::disk('local')->delete('mixing/'.$mixing->id.'/'.$file->file_name);
        $file->delete();
        $this->log($user, 'DELETE', $mixing, ['file' => $file->original_name]);
    }

    /** @param array<string, mixed> $data */
    private function log(User $user, string $action, Mixing $mixing, array $data): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'MIXING_ATTACHMENT',
            'record_id' => (string) $mixing->id,
            'record_label' => $mixing->mixing_code,
            'old_data' => null,
            'new_data' => json_encode($data),
        ]);
    }
}
