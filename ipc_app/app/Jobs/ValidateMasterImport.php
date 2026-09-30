<?php

namespace App\Jobs;

use App\Imports\MasterImportRunner;
use App\Models\MasterImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ValidateMasterImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $importId) {}

    public function handle(MasterImportRunner $runner): void
    {
        $import = MasterImport::find($this->importId);
        if ($import !== null) {
            $runner->validate($import);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $import = MasterImport::find($this->importId);
        if ($import !== null && ! in_array($import->status, MasterImport::FINAL_STATUSES, true)) {
            app(MasterImportRunner::class)->finish($import, MasterImport::STATUS_FAILED, 'Validasi gagal karena kesalahan sistem. Tidak ada data yang tersimpan. Silakan upload ulang.');
        }
    }
}
