<?php

namespace App\Console\Commands;

use App\Models\MasterLine;
use App\Services\MachineMaster\MachineLineDirectory;
use Illuminate\Console\Command;

class MapLinesToMachineMaster extends Command
{
    protected $signature = 'lines:map-machine-master {--apply : Write the suggested mapping (default is a dry run)}';

    protected $description = 'Suggest/fill master_lines.machine_line_code from the Machine Master by normalising codes (SC 01 -> SC-01). Never overwrites an existing link.';

    public function handle(): int
    {
        $known = array_column(MachineLineDirectory::options(), 'code');
        if ($known === []) {
            $this->error('Machine Master is empty or unreachable (connection '.MachineLineDirectory::connectionName().').');

            return self::FAILURE;
        }

        $rows = [];
        $apply = (bool) $this->option('apply');
        foreach (MasterLine::query()->whereNull('machine_line_code')->orderBy('category')->orderBy('code')->get() as $line) {
            $suggestion = MachineLineDirectory::suggest($line->code, $known);
            $rows[] = [$line->id, $line->category, $line->code, $suggestion ?? '(manual)'];
            if ($apply && $suggestion) {
                $line->machine_line_code = $suggestion;
                $line->save();
            }
        }

        $this->table(['ID', 'Category', 'IPC code', 'Machine Master'], $rows);
        $matched = count(array_filter($rows, fn ($r) => $r[3] !== '(manual)'));
        $this->info(($apply ? 'Linked ' : 'Would link ').$matched.' of '.count($rows).' unlinked line(s); the rest need a manual choice in Master Line.');

        return self::SUCCESS;
    }
}
