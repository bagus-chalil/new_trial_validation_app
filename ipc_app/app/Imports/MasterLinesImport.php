<?php

namespace App\Imports;

use App\Models\MasterLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Upserts master_lines by kode_line ("Kode Line" — the line's code, e.g. "MU 01").
 * There is no DB-level unique constraint on master_lines.code (admin CRUD upserts by id
 * instead), but the code is the only column an import file can realistically key off.
 */
class MasterLinesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    /** @var list<string> */
    public array $rowErrors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $category = trim((string) ($row['kategori'] ?? ''));
            $area = trim((string) ($row['area'] ?? ''));
            $code = trim((string) ($row['kode_line'] ?? ''));
            $name = trim((string) ($row['nama_line'] ?? ''));

            if ($category === '' && $area === '' && $code === '' && $name === '') {
                continue;
            }

            $validator = Validator::make(
                ['kategori' => $category, 'area' => $area, 'kode_line' => $code, 'nama_line' => $name],
                [
                    'kategori' => ['required', 'string', 'max:100'],
                    'area' => ['required', 'string', 'max:100'],
                    'kode_line' => ['required', 'string', 'max:50'],
                    'nama_line' => ['required', 'string', 'max:150'],
                ],
            );

            if ($validator->fails()) {
                $this->rowErrors[] = "Baris {$rowNumber}: {$validator->errors()->first()}";

                continue;
            }

            $line = MasterLine::where('code', $code)->first();
            $isNew = $line === null;
            $line ??= new MasterLine(['code' => $code]);
            $line->category = $category;
            $line->area = $area;
            $line->name = $name;
            $line->is_active = $this->parseActive($row['status_line'] ?? null);
            $line->save();

            $isNew ? $this->created++ : $this->updated++;
        }
    }

    private function parseActive(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return ! in_array($normalized, ['nonaktif', 'non aktif', 'tidak aktif', 'n', 'no', 'false', '0'], true);
    }

    public function summary(): string
    {
        return sprintf('%d line baru, %d line diperbarui.', $this->created, $this->updated);
    }

    public function hasChanges(): bool
    {
        return $this->created + $this->updated > 0;
    }
}
