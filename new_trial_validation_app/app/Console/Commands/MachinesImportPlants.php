<?php

namespace App\Console\Commands;

use App\Models\MachineLine;
use App\Models\Plant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[Signature('machines:import-plants {file : CSV or xlsx with columns Line ID, Plant Code, Plant Name, Building, Floor, Area} {--dry-run : Validate and report only} {--template : Instead of importing, write FILE as a CSV template pre-filled with every current line}')]
#[Description('Bulk-fill plant / building / floor (and optionally area) per line from a sheet. Idempotent by Line ID; blank cells never erase existing values.')]
class MachinesImportPlants extends Command
{
    private const HEADERS = ['Line ID', 'Plant Code', 'Plant Name', 'Building', 'Floor', 'Area'];

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if ($this->option('template')) {
            return $this->writeTemplate($file);
        }
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $rows = $this->read($file);
        if ($rows === null) {
            $this->error('Header must contain: '.implode(', ', self::HEADERS));

            return self::FAILURE;
        }

        $lines = MachineLine::query()->get()->keyBy('line_code');
        $plants = Plant::withTrashed()->get()->keyBy('code');
        $errors = [];
        $plan = [];
        // A plant code may be defined (with its name) on any row of the file.
        $namedInFile = collect($rows)->filter(fn ($r) => trim($r['Plant Code']) !== '' && trim($r['Plant Name']) !== '')
            ->map(fn ($r) => strtoupper(trim($r['Plant Code'])))->unique()->all();

        foreach ($rows as $n => $r) {
            $code = strtoupper(trim($r['Line ID']));
            if ($code === '') {
                continue;
            }
            if (! $lines->has($code)) {
                $errors[] = "Row {$n}: unknown Line ID {$code}.";

                continue;
            }
            $plantCode = strtoupper(trim($r['Plant Code']));
            if ($plantCode !== '' && ! $plants->has($plantCode) && ! in_array($plantCode, $namedInFile, true)) {
                $errors[] = "Row {$n}: plant {$plantCode} does not exist yet, so Plant Name is required.";

                continue;
            }
            if ($plantCode !== '' && ($plants[$plantCode] ?? null)?->trashed()) {
                $errors[] = "Row {$n}: plant {$plantCode} is deleted. Restore or use another code.";

                continue;
            }
            $plan[] = ['line' => $lines[$code], 'plant_code' => $plantCode, 'plant_name' => trim($r['Plant Name']),
                'building' => trim($r['Building']), 'floor' => trim($r['Floor']), 'area' => trim($r['Area'])];
        }

        foreach ($errors as $e) {
            $this->warn($e);
        }
        $newPlants = collect($plan)->filter(fn ($p) => $p['plant_code'] !== '' && ! $plants->has($p['plant_code']))
            ->unique('plant_code')->count();
        $this->line(sprintf('%d line row(s) to update, %d new plant(s), %d problem row(s).', count($plan), $newPlants, count($errors)));

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing written.');

            return self::SUCCESS;
        }
        if ($errors !== []) {
            $this->error('Fix the problems above first (nothing written).');

            return self::FAILURE;
        }

        $namedNames = collect($rows)->filter(fn ($r) => trim($r['Plant Code']) !== '' && trim($r['Plant Name']) !== '')
            ->mapWithKeys(fn ($r) => [strtoupper(trim($r['Plant Code'])) => trim($r['Plant Name'])])->all();

        DB::transaction(function () use ($plan, $plants, $namedNames) {
            foreach ($plan as $p) {
                $plant = $p['plant_code'] !== '' ? ($plants[$p['plant_code']] ?? null) : null;
                if ($p['plant_code'] !== '' && ! $plant) {
                    $plant = Plant::create(['code' => $p['plant_code'], 'name' => $namedNames[$p['plant_code']], 'is_active' => true]);
                    $plants[$p['plant_code']] = $plant;
                }
                $set = array_filter([
                    'plant_id' => $plant?->id,
                    'building' => $p['building'] ?: null,
                    'floor' => $p['floor'] ?: null,
                    'area' => $p['area'] ?: null,
                ], fn ($v) => $v !== null);
                if ($set !== []) {
                    $p['line']->forceFill($set)->save();
                }
            }
        });
        $this->info('Imported.');

        return self::SUCCESS;
    }

    /** @return array<int, array<string, string>>|null */
    private function read(string $file): ?array
    {
        $sheet = IOFactory::load($file)->getActiveSheet()->toArray(null, true, false);
        $header = array_map(fn ($h) => trim((string) $h), array_shift($sheet) ?? []);
        $index = [];
        foreach (self::HEADERS as $h) {
            $pos = array_search($h, $header, true);
            if ($pos === false) {
                return null;
            }
            $index[$h] = $pos;
        }

        $rows = [];
        foreach ($sheet as $i => $r) {
            $row = [];
            foreach ($index as $h => $pos) {
                $row[$h] = (string) ($r[$pos] ?? '');
            }
            $rows[$i + 2] = $row;
        }

        return $rows;
    }

    private function writeTemplate(string $file): int
    {
        $fh = fopen($file, 'w');
        if (! $fh) {
            $this->error("Cannot write {$file}");

            return self::FAILURE;
        }
        fputcsv($fh, self::HEADERS, escape: '');
        $plants = Plant::query()->get()->keyBy('id');
        foreach (MachineLine::query()->orderBy('line_code')->get() as $l) {
            $p = $plants->get($l->plant_id);
            fputcsv($fh, [$l->line_code, $p?->code, $p?->name, $l->building, $l->floor, $l->area], escape: '');
        }
        fclose($fh);
        $this->info("Template written: {$file}");

        return self::SUCCESS;
    }
}
