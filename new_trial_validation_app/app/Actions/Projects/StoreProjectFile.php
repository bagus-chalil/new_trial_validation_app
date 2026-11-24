<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectQuotation;
use App\Models\User;
use App\Support\ProjectUploadGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Stores one uploaded file on the private `project_files` disk. Mirrors the
 * legacy Project Tracking checks: extension whitelist, detected MIME must
 * match the extension, script-like names rejected anywhere in the file name,
 * size cap. Throws InvalidArgumentException with a translated message when
 * the file is refused (the controller turns it into a validation error).
 */
class StoreProjectFile
{
    public function __construct(
        private readonly LogProjectActivity $log,
        private readonly DeleteProjectFile $deleteFile,
    ) {}

    public function __invoke(Project $project, UploadedFile $upload, string $context, ?int $stageId, ?int $quotationId, ?string $description, User $user): ProjectFile
    {
        $originalName = $upload->getClientOriginalName();
        ['extension' => $extension, 'mime' => $detected] = ProjectUploadGuard::check($upload);

        $stored = bin2hex(random_bytes(16)).'.'.$extension;

        if (Storage::disk('project_files')->putFileAs('', $upload, $stored) === false) {
            throw new InvalidArgumentException(__('projects.upload.store_failed', ['name' => $originalName]));
        }

        $file = ProjectFile::create([
            'project_id' => $project->id,
            'stage_id' => $context === 'stage' ? $stageId : null,
            'context' => $context,
            'file_name' => mb_substr($originalName, 0, 255),
            'mime_type' => $detected,
            'size_bytes' => (int) $upload->getSize(),
            'stored_name' => $stored,
            'description' => $description,
            'uploaded_by_name' => $user->name,
            'uploaded_at' => Carbon::now(),
        ]);

        if ($context === 'quotation' && $quotationId) {
            $quotation = ProjectQuotation::where('project_id', $project->id)->find($quotationId);

            if ($quotation) {
                // One file per quotation: a new upload replaces the old one.
                $previous = $quotation->file_id ? ProjectFile::find($quotation->file_id) : null;
                $quotation->file_id = $file->id;
                $quotation->save();

                if ($previous) {
                    ($this->deleteFile)($project, $previous, $user, log: false);
                }
            }
        }

        ($this->log)($user, 'UPLOAD_FILE', $project->id, $project->name, null, ['file' => $file->file_name, 'context' => $context]);

        return $file;
    }
}
