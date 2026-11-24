<?php

namespace App\Actions\Improvements;

use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/** Hard delete of the row and the physical file (when nothing else points at it). */
class DeleteImprovementFile
{
    public function __construct(private readonly LogImprovementActivity $log) {}

    public function __invoke(Improvement $improvement, ImprovementFile $file, User $user): void
    {
        $name = $file->file_name;
        $stored = basename($file->stored_name);
        $file->delete();

        if ($stored !== ''
            && ! ImprovementFile::where('stored_name', $stored)->exists()
            && ! ProjectFile::where('stored_name', $stored)->exists()) {
            Storage::disk('project_files')->delete($stored);
        }

        ($this->log)($user, 'DELETE_FILE', $improvement, ['file' => $name]);
    }
}
