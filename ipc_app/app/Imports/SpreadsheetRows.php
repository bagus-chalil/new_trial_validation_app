<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads the first sheet of an uploaded spreadsheet into heading-keyed rows (headings slugged
 * by maatwebsite, e.g. "Shelf Life (Bulan)" -> shelf_life_bulan), keeping each row's real
 * spreadsheet row number and dropping fully blank rows.
 */
class SpreadsheetRows
{
    /**
     * @return array{headings: list<string>, rows: list<array{row: int, data: array<string, string>}>}
     */
    public static function read(string $path, ?string $disk = 'local'): array
    {
        $sheets = Excel::toArray(new class implements Import, WithHeadingRow {}, $path, $disk);
        $sheet = $sheets[0] ?? [];

        $headings = [];
        $rows = [];

        foreach ($sheet as $index => $raw) {
            if ($headings === []) {
                $headings = array_values(array_filter(array_map('strval', array_keys($raw)), fn ($key) => $key !== '' && ! is_numeric($key)));
            }

            $data = [];
            foreach ($raw as $key => $value) {
                if (is_string($key) && $key !== '') {
                    $data[$key] = self::normalize($value);
                }
            }

            if (implode('', $data) === '') {
                continue;
            }

            $rows[] = ['row' => $index + 2, 'data' => $data]; // +1 for 0-index, +1 for the heading row
        }

        return ['headings' => $headings, 'rows' => $rows];
    }

    /** Excel stores codes like 621106005 as numbers; turn 621106005.0 back into "621106005". */
    private static function normalize(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return sprintf('%.0f', $value);
        }

        return trim((string) ($value ?? ''));
    }
}
