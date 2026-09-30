<?php

namespace App\Imports;

use App\Models\MasterLine;

/**
 * Master Line import, keyed by "Kode Line" (e.g. "MU 01"). There is no DB-level unique
 * constraint on master_lines.code (admin CRUD upserts by id), but the code is the only column
 * an import file can realistically key off. The first row for a code wins; a later row with the
 * same code is skipped with a warning (identical ones are flagged as plain duplicates).
 */
class MasterLinesImportProcessor implements MasterImportProcessor
{
    private const INACTIVE_VALUES = ['nonaktif', 'non aktif', 'tidak aktif', 'n', 'no', 'false', '0'];

    public function label(): string
    {
        return 'Master Line';
    }

    public function requiredHeadings(): array
    {
        return ['kategori' => 'Kategori', 'area' => 'Area', 'kode_line' => 'Kode Line', 'nama_line' => 'Nama Line'];
    }

    public function columns(): array
    {
        return ['kategori' => 'Kategori', 'area' => 'Area', 'kode_line' => 'Kode Line', 'nama_line' => 'Nama Line', 'status_line' => 'Status Line'];
    }

    public function plan(array $rows, callable $progress): ImportPlan
    {
        $plan = new ImportPlan;
        $plan->totalRows = count($rows);

        /** @var array<string, array{row: int, values: array<string, string>}> $lines */
        $lines = [];

        foreach ($rows as $i => ['row' => $rowNumber, 'data' => $data]) {
            $values = [];
            foreach (array_keys($this->columns()) as $key) {
                $values[$key] = $data[$key] ?? '';
            }

            $errors = $this->validate($values);
            if ($errors !== []) {
                $plan->error($rowNumber, implode(' ', $errors), $values);

                continue;
            }

            $codeKey = mb_strtolower($values['kode_line']);
            if (isset($lines[$codeKey])) {
                $first = $lines[$codeKey];
                $message = $first['values'] === $values
                    ? "Duplikat identik dengan baris {$first['row']}, dilewati."
                    : "Kode Line {$values['kode_line']} sudah ada di baris {$first['row']} dengan data berbeda. Baris ini dilewati, yang dipakai data baris {$first['row']}.";
                $plan->warning($rowNumber, $message, $values);

                continue;
            }

            $lines[$codeKey] = ['row' => $rowNumber, 'values' => $values];
            $plan->validRows++;

            if (($i + 1) % 250 === 0) {
                $progress($i + 1, $plan->totalRows);
            }
        }

        $existing = MasterLine::query()->orderBy('id')->get()->unique(fn (MasterLine $line) => mb_strtolower($line->code))
            ->keyBy(fn (MasterLine $line) => mb_strtolower($line->code));

        $preview = ['lines_new' => 0, 'lines_updated' => 0, 'lines_unchanged' => 0];
        foreach ($lines as $codeKey => ['values' => $values]) {
            $line = $existing->get($codeKey);
            $attributes = [
                'category' => $values['kategori'],
                'area' => $values['area'],
                'code' => $values['kode_line'],
                'name' => $values['nama_line'],
                'is_active' => $this->parseActive($values['status_line']),
            ];

            $action = match (true) {
                $line === null => 'new',
                $line->category !== $attributes['category'] || $line->area !== $attributes['area']
                    || $line->name !== $attributes['name'] || $line->is_active !== $attributes['is_active'] => 'updated',
                default => 'unchanged',
            };

            $preview['lines_'.$action]++;
            $plan->operations[] = ['action' => $action, 'line_id' => $line?->id, 'attributes' => $attributes];
        }

        $preview['skipped_duplicates'] = $plan->totalRows - $plan->validRows - $plan->errorRows();
        $plan->preview = $preview;
        $progress($plan->totalRows, $plan->totalRows);

        return $plan;
    }

    public function apply(ImportPlan $plan, callable $progress): array
    {
        $result = array_fill_keys(array_keys($plan->preview), 0);
        $result['skipped_duplicates'] = $plan->preview['skipped_duplicates'] ?? 0;
        $total = count($plan->operations);

        foreach ($plan->operations as $i => $op) {
            if ($op['action'] !== 'unchanged') {
                $line = $op['line_id'] !== null ? MasterLine::findOrFail($op['line_id']) : new MasterLine;
                $line->fill($op['attributes']);
                $line->is_active = $op['attributes']['is_active'];
                $line->save();
            }
            $result['lines_'.$op['action']]++;

            if (($i + 1) % 200 === 0) {
                $progress($i + 1, $total);
            }
        }
        $progress($total, $total);

        return $result;
    }

    /** @return list<string> */
    private function validate(array $values): array
    {
        $limits = ['kategori' => 100, 'area' => 100, 'kode_line' => 50, 'nama_line' => 150];
        $errors = [];

        foreach ($limits as $key => $max) {
            $label = $this->columns()[$key];
            if ($values[$key] === '') {
                $errors[] = "{$label} wajib diisi.";
            } elseif (mb_strlen($values[$key]) > $max) {
                $errors[] = "{$label} maksimal {$max} karakter.";
            }
        }

        return $errors;
    }

    private function parseActive(string $value): bool
    {
        return $value === '' || ! in_array(mb_strtolower($value), self::INACTIVE_VALUES, true);
    }
}
