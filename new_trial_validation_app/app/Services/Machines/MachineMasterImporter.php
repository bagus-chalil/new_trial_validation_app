<?php

namespace App\Services\Machines;

use App\Models\Equipment;
use App\Models\MachineLine;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Imports "Cosmax Line Specification Database 2026.xlsx" (sheets Line_Master +
 * Equipment_List) into mc_lines / mc_equipment. Idempotent: rows are matched on
 * their own Line ID / Equipment ID, so re-running updates in place. plan() reads
 * only; apply() writes in one transaction. Plant/location columns the file does
 * not carry (plant, building, floor) are never touched on update.
 */
class MachineMasterImporter
{
    /** @return array{lines: array<int, array<string, mixed>>, equipment: array<int, array<string, mixed>>, warnings: array<int, string>} */
    public function plan(string $path): array
    {
        $book = IOFactory::load($path);
        $warnings = [];

        $lines = [];
        $ws = $book->getSheetByName('Line_Master');
        foreach ($ws?->toArray(null, true, false) ?? [] as $i => $r) {
            if ($i < 4 || blank($r[0] ?? null)) {
                continue;
            }
            $lines[] = [
                'line_code' => strtoupper(trim((string) $r[0])),
                'category' => $this->str($r[1]) ?? 'Skincare',
                'line_type' => $this->str($r[2]),
                'name' => $this->str($r[3]) ?? trim((string) $r[0]),
                'area' => $this->str($r[4]),
                'status' => $this->str($r[5]) ?? 'To be verified',
                'products' => $this->str($r[6]),
                'rated_capacity' => $this->num($r[7]),
                'capacity_unit' => $this->str($r[8]),
                'pack_format' => $this->str($r[9]),
                'automation_level' => $this->str($r[10]),
                'manpower' => $this->int($r[11]),
                'pic' => $this->str($r[12]),
                'layout_link' => $this->str($r[13]),
                'tooling_link' => $this->str($r[14]),
                'remarks' => $this->str($r[16]),
            ];
        }

        $codes = array_column($lines, 'line_code');
        if (count($codes) !== count(array_unique($codes))) {
            $warnings[] = 'Duplicate Line ID in Line_Master.';
        }

        $equipment = [];
        $ws = $book->getSheetByName('Equipment_List');
        foreach ($ws?->toArray(null, true, false) ?? [] as $i => $r) {
            if ($i < 4 || blank($r[0] ?? null)) {
                continue;
            }
            $lineCode = strtoupper(trim((string) $r[0]));
            if (! in_array($lineCode, $codes, true)) {
                $warnings[] = "Equipment {$r[2]} references unknown line {$lineCode}.";

                continue;
            }
            [$manual, $country, $remarks] = $this->parseRemarks($this->str($r[26]));
            $equipment[] = [
                'line_code' => $lineCode,
                'equipment_code' => strtoupper(trim((string) $r[2])),
                'process_step' => $this->int($r[1]),
                'name' => $this->str($r[3]) ?? trim((string) $r[2]),
                'equipment_type' => $this->str($r[4]),
                'manufacturer' => $this->str($r[5]),
                'model' => $this->str($r[6]),
                'serial_no' => $this->str($r[7]),
                'year_made' => $this->int($r[8]),
                'country' => $country,
                'rated_capacity' => $this->num($r[9]),
                'capacity_unit' => $this->str($r[10]),
                'max_capacity' => $this->num($r[11]),
                'heads' => $this->str($r[12]),
                'working_volume' => $this->str($r[13]),
                'temp_range' => $this->str($r[14]),
                'pressure' => $this->str($r[15]),
                'power_kw' => $this->str($r[16]),
                'voltage' => $this->str($r[17]),
                'compressed_air' => $this->str($r[18]),
                'other_utility' => $this->str($r[19]),
                'dimension' => $this->str($r[20]),
                'weight_kg' => $this->str($r[21]),
                'other_spec' => $this->str($r[22]),
                'condition' => $this->str($r[23]),
                'manual_status' => $manual,
                'manual_link' => $this->str($r[24]),
                'drawing_link' => $this->str($r[25]),
                'remarks' => $remarks,
                'qty' => $this->int($r[29]),
                'units_good' => $this->int($r[30] ?? null),
                'units_attention' => $this->int($r[31] ?? null),
                'units_maintenance' => $this->int($r[32] ?? null),
                'units_out_of_service' => $this->int($r[33] ?? null),
                'units_to_verify' => $this->int($r[34] ?? null),
                'condition_detail' => $this->str($r[36] ?? null),
            ];
        }
        $eqCodes = array_column($equipment, 'equipment_code');
        if (count($eqCodes) !== count(array_unique($eqCodes))) {
            $warnings[] = 'Duplicate Equipment ID in Equipment_List.';
        }

        return ['lines' => $lines, 'equipment' => $equipment, 'warnings' => $warnings];
    }

    /**
     * @param  array{lines: array<int, array<string, mixed>>, equipment: array<int, array<string, mixed>>, warnings: array<int, string>}  $plan
     * @return array{lines_created: int, lines_updated: int, equipment_created: int, equipment_updated: int}
     */
    public function apply(array $plan): array
    {
        $stats = ['lines_created' => 0, 'lines_updated' => 0, 'equipment_created' => 0, 'equipment_updated' => 0];

        DB::transaction(function () use ($plan, &$stats) {
            $ids = [];
            foreach ($plan['lines'] as $row) {
                $line = MachineLine::withTrashed()->firstOrNew(['line_code' => $row['line_code']]);
                $line->exists ? $stats['lines_updated']++ : $stats['lines_created']++;
                $line->fill(array_diff_key($row, ['line_code' => 1]));
                $line->line_code = $row['line_code'];
                $line->is_active = true;
                $line->deleted_at = null;
                $line->save();
                $ids[$row['line_code']] = $line->id;
            }
            foreach ($plan['equipment'] as $row) {
                $eq = Equipment::withTrashed()->firstOrNew(['equipment_code' => $row['equipment_code']]);
                $eq->exists ? $stats['equipment_updated']++ : $stats['equipment_created']++;
                $eq->fill(array_diff_key($row, ['line_code' => 1, 'equipment_code' => 1]));
                $eq->line_id = $ids[$row['line_code']];
                $eq->equipment_code = $row['equipment_code'];
                $eq->is_active = true;
                $eq->deleted_at = null;
                $eq->save();
            }
        });

        return $stats;
    }

    /**
     * The sheet stores "Manual Book: Ada | Negara pembuat: Korea Selatan | Teknologi: Auto | free text".
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function parseRemarks(?string $text): array
    {
        $manual = null;
        $country = null;
        $rest = [];
        foreach (array_filter(array_map('trim', explode('|', (string) $text))) as $part) {
            if (preg_match('/^Manual Book:\s*(.+)$/iu', $part, $m)) {
                $v = trim($m[1]);
                $manual = match (true) {
                    stripos($v, 'ada (di produksi)') === 0 => 'Ada (di Produksi)',
                    stripos($v, 'ada') === 0 => 'Ada',
                    stripos($v, 'tidak') === 0 => 'Tidak Ada',
                    default => null, // "Belum tercatat (perlu cek fisik)" = unknown
                };
                if ($manual === null) {
                    $rest[] = $part;
                }
            } elseif (preg_match('/^Negara pembuat:\s*(.+)$/iu', $part, $m)) {
                $country = trim($m[1]);
            } else {
                $rest[] = $part;
            }
        }

        return [$manual, $country, $rest ? implode(' | ', $rest) : null];
    }

    private function str(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
