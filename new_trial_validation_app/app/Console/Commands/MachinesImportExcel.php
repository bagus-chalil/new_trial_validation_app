<?php

namespace App\Console\Commands;

use App\Services\Machines\MachineMasterImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('machines:import-excel {file : Path to "Cosmax Line Specification Database 2026.xlsx"} {--dry-run : Read and report only, write nothing}')]
#[Description('Import the maintainers\' Line Master + Equipment List into mc_lines / mc_equipment (idempotent by Line ID / Equipment ID).')]
class MachinesImportExcel extends Command
{
    public function handle(MachineMasterImporter $importer): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $plan = $importer->plan($file);
        $this->line(sprintf('Read %d line(s), %d equipment row(s).', count($plan['lines']), count($plan['equipment'])));
        foreach ($plan['warnings'] as $w) {
            $this->warn($w);
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing written.');

            return self::SUCCESS;
        }
        if ($plan['warnings'] !== []) {
            $this->error('Fix the warnings above first.');

            return self::FAILURE;
        }

        $s = $importer->apply($plan);
        $this->info(sprintf('Lines: %d created, %d updated. Equipment: %d created, %d updated.', $s['lines_created'], $s['lines_updated'], $s['equipment_created'], $s['equipment_updated']));

        return self::SUCCESS;
    }
}
