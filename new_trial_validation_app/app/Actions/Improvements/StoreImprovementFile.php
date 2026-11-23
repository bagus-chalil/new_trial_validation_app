<?php

namespace App\Actions\Improvements;

use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\User;
use App\Support\ProjectUploadGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Stores one improvement attachment on the private `project_files` disk (same checks as project files). */
class StoreImprovementFile
{
    public function __construct(private readonly LogImprovementActivity $log) {}

    public function __invoke(Improvement $improvement, UploadedFile $upload, ?string $description, User $user, string $phase = 'before'): ImprovementFile
    {
        $originalName = $upload->getClientOriginalName();
        ['extension' => $extension, 'mime' => $detected] = ProjectUploadGuard::check($upload);

        $stored = bin2hex(random_bytes(16)).'.'.$extension;

        if (Storage::disk('project_files')->putFileAs('', $upload, $stored) === false) {
            throw new InvalidArgumentException(__('projects.upload.store_failed', ['name' => $originalName]));
        }

        $file = ImprovementFile::create([
            'improvement_id' => $improvement->id,
            'file_name' => mb_substr($originalName, 0, 255),
            'mime_type' => $detected,
            'size_bytes' => (int) $upload->getSize(),
            'stored_name' => $stored,
            'description' => $description,
            'uploaded_at' => Carbon::now(),
            'phase' => $phase === 'after' ? 'after' : 'before',
        ]);

        ($this->log)($user, 'UPLOAD_FILE', $improvement, null, ['file' => $file->file_name]);

        return $file;
    }
}
