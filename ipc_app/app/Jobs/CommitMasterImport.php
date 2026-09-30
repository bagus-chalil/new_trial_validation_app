<?php

namespace App\Jobs;

use App\Imports\MasterImportRunner;
use App\Models\MasterImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CommitMasterImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $importId) {}

    public function handle(MasterImportRunner $runner): void
    {
        $import = MasterImport::find($this->importId);
        if ($import !== null) {
            $runner->commit($import);
        }
    }

    /** Reached when the worker kills the job (timeout/crash); the open transaction died with it, so nothing was saved. */
    public function failed(?Throwable $exception): void
    {
        $import = MasterImport::find($this->importId);
        if ($import !== null && ! in_array($import->status, MasterImport::FINAL_STATUSES, true)) {
            app(MasterImportRunner::class)->finish($import, MasterImport::STATUS_FAILED, 'Penyimpanan terhenti karena kesalahan sistem (mis. waktu habis) dan dibatalkan seluruhnya (rollback). Tidak ada data yang tersimpan. Silakan coba lagi.');
        }
    }
}
