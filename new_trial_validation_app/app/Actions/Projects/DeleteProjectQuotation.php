<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectQuotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteProjectQuotation
{
    public function __construct(
        private readonly LogProjectActivity $log,
        private readonly DeleteProjectFile $deleteFile,
    ) {}

    public function __invoke(Project $project, ProjectQuotation $quotation, User $user): void
    {
        DB::transaction(function () use ($project, $quotation, $user) {
            $file = $quotation->file_id ? ProjectFile::find($quotation->file_id) : null;
            $vendor = $quotation->vendor_name;
            $quotation->delete();

            if ($file) {
                ($this->deleteFile)($project, $file, $user, log: false);
            }

            ($this->log)($user, 'DELETE_QUOTATION', $project->id, $project->name, ['vendor' => $vendor]);
        });
    }
}
