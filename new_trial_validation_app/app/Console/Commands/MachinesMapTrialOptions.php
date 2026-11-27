<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\MachineLine;
use App\Models\MachineOptionMap;
use App\Services\Machines\MachineOptionMatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('machines:map-trial-options {--apply : Write the suggestions to mc_option_maps (default is a dry run)}')]
#[Description('Suggest a machine-master line/equipment for every Trial machine_used option name. Never touches trials, master_options or rows an admin set by hand.')]
class MachinesMapTrialOptions extends Command
{
    public function handle(): int
    {
        $lines = MachineLine::query()->get();
        if ($lines->isEmpty()) {
            $this->error('mc_lines is empty. Run machines:import-excel first.');

            return self::FAILURE;
        }

        $matcher = new MachineOptionMatcher($lines, Equipment::query()->get());
        $usage = MachineOptionMap::usageCounts();
        $existing = MachineOptionMap::query()->get()->keyBy('option_name');
        $lineCodes = $lines->pluck('line_code', 'id');
        $equipmentCodes = Equipment::query()->pluck('equipment_code', 'id');

        $rows = [];
        $written = ['auto' => 0, 'suggested' => 0];
        $none = 0;
        $skipped = 0;

        foreach (MachineOptionMap::optionNames($usage) as $name) {
            $current = $existing->get($name);
            if ($current && in_array($current->status, MachineOptionMap::LOCKED, true)) {
                $skipped++;

                continue;
            }

            $s = $matcher->suggest($name);
            if (! $s) {
                $none++;
                $rows[] = [$name, $usage[$name] ?? 0, '-', '-', '-', 'no suggestion'];

                continue;
            }

            $status = $s['score'] >= MachineOptionMatcher::AUTO_THRESHOLD ? MachineOptionMap::AUTO : MachineOptionMap::SUGGESTED;
            $written[$status]++;
            $rows[] = [
                $name,
                $usage[$name] ?? 0,
                $lineCodes[$s['line_id']] ?? '?',
                $s['equipment_id'] ? ($equipmentCodes[$s['equipment_id']] ?? '?') : '-',
                number_format($s['score'], 2),
                $status.' ('.$s['reason'].')',
            ];

            if ($this->option('apply')) {
                MachineOptionMap::updateOrCreate(['option_name' => $name], [
                    'line_id' => $s['line_id'],
                    'equipment_id' => $s['equipment_id'],
                    'status' => $status,
                    'score' => $s['score'],
                    'reason' => $s['reason'],
                ]);
            }
        }

        $this->table(['Option', 'Trials', 'Line', 'Equipment', 'Score', 'Result'], $rows);
        $this->line(sprintf(
            '%d auto, %d suggested (need review), %d without suggestion, %d admin-decided rows left untouched.',
            $written['auto'], $written['suggested'], $none, $skipped,
        ));
        $this->info($this->option('apply') ? 'Applied.' : 'Dry run: nothing written. Use --apply to save.');

        return self::SUCCESS;
    }
}
