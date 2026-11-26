<?php

namespace App\Console\Commands;

use App\Actions\Validation\ImportFgMappings;
use Illuminate\Console\Command;

class ValidationImportFg extends Command
{
    protected $signature = 'validation:import-fg {file : CSV or XLSX exported from iERP (fg_code, item_name, effective_date)} {--dry-run : Run everything, then roll back (writes nothing)}';

    protected $description = 'Import the official FG list from iERP into vd_fg_mappings (idempotent, never deletes)';

    public function handle(ImportFgMappings $import): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $stats = $import($file, (bool) $this->option('dry-run'));
        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info($this->option('dry-run') ? 'Dry run: nothing was written.' : 'Import done.');

        return self::SUCCESS;
    }
}
