<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectQuotation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/** Hard delete of the row and the physical file (same as legacy; attachments have no recycle bin). */
class DeleteProjectFile
{
    public function __construct(private readonly LogProjectActivity $log) {}

    public function __invoke(Project $project, ProjectFile $file, User $user, bool $log = true): void
    {
        ProjectQuotation::where('file_id', $file->id)->update(['file_id' => null]);

        $name = $file->file_name;
        $stored = basename($file->stored_name);
        $file->delete();

        // Only drop the physical file when nothing else points at it.
        if ($stored !== '' && ! ProjectFile::where('stored_name', $stored)->exists()) {
            Storage::disk('project_files')->delete($stored);
        }

        if ($log) {
            ($this->log)($user, 'DELETE_FILE', $project->id, $project->name, ['file' => $name]);
        }
    }
}
