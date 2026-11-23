<?php

namespace App\Actions\Toolings;

use App\Models\ProjectFile;
use App\Models\Tooling;
use App\Models\User;
use App\Support\ProjectUploadGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Check Tooling attachments: rows in pm_files (context=tooling, tooling_id),
 * physical files on the private `project_files` disk, same upload checks as
 * project and improvement files (ProjectUploadGuard).
 */
class ToolingFiles
{
    public function __construct(private readonly LogToolingActivity $log) {}

    public function store(Tooling $tooling, UploadedFile $upload, ?string $description, User $user): ProjectFile
    {
        $originalName = $upload->getClientOriginalName();
        ['extension' => $extension, 'mime' => $detected] = ProjectUploadGuard::check($upload);

        $stored = bin2hex(random_bytes(16)).'.'.$extension;

        if (Storage::disk('project_files')->putFileAs('', $upload, $stored) === false) {
            throw new InvalidArgumentException(__('projects.upload.store_failed', ['name' => $originalName]));
        }

        return DB::transaction(function () use ($tooling, $upload, $description, $user, $originalName, $detected, $stored) {
            $file = ProjectFile::create([
                'tooling_id' => $tooling->id,
                'context' => 'tooling',
                'file_name' => mb_substr($originalName, 0, 255),
                'mime_type' => $detected,
                'size_bytes' => (int) $upload->getSize(),
                'stored_name' => $stored,
                'description' => $description,
                'uploaded_by_name' => mb_substr($user->name, 0, 100),
                'uploaded_at' => Carbon::now(),
            ]);

            ($this->log)($user, 'UPLOAD_FILE', $tooling, $file->file_name, null, ['file' => $file->file_name]);
            $tooling->save();

            return $file;
        });
    }

    public function destroy(Tooling $tooling, ProjectFile $file, User $user): void
    {
        DB::transaction(function () use ($tooling, $file, $user) {
            $stored = basename($file->stored_name);
            $name = $file->file_name;
            $file->delete();

            // Imported files may share a stored name with nothing else, but
            // only remove the physical file when no other row points at it.
            if ($stored !== '' && ! ProjectFile::where('stored_name', $file->stored_name)->exists()) {
                Storage::disk('project_files')->delete($stored);
            }

            ($this->log)($user, 'DELETE_FILE', $tooling, $name, ['file' => $name], null);
            $tooling->save();
        });
    }

    /** Soft delete of the whole request (Admin only). */
    public function deleteTooling(Tooling $tooling, User $user): void
    {
        DB::transaction(function () use ($tooling, $user) {
            $tooling->deleted_at = Carbon::now();
            $tooling->deleted_by = $user->id;
            ($this->log)($user, 'DELETE', $tooling, null, ['status' => $tooling->status], null);
            $tooling->save();
        });
    }
}
