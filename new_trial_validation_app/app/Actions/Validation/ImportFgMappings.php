<?php

namespace App\Actions\Validation;

use App\Models\FgMapping;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports the official FG list exported from iERP (CSV or XLSX; columns
 * fg_code, item_name, optional effective_date). Idempotent by fg_code, never
 * deletes, never overwrites verification: a changed item name is kept in
 * item_name_previous. Rows whose code matches exactly one active product get
 * that product_id as a proposal (verification stays a human step).
 * FG batch numbers are not imported here (they belong to the IPC run, Tahap 7).
 */
class ImportFgMappings
{
    /** @return array{read: int, created: int, updated: int, unchanged: int, matched: int, unmatched: int, skipped: int} */
    public function __invoke(string $path, bool $dryRun = false): array
    {
        $rows = $this->readRows($path);
        $stats = ['read' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'matched' => 0, 'unmatched' => 0, 'skipped' => 0];

        $run = function () use ($rows, &$stats): void {
            foreach ($rows as $row) {
                $stats['read']++;
                $code = trim((string) ($row['fg_code'] ?? ''));
                $name = trim((string) ($row['item_name'] ?? ''));
                if ($code === '' || $name === '') {
                    $stats['skipped']++;

                    continue;
                }

                $products = Product::query()->whereNull('deleted_at')->where('finish_good_code', $code)->pluck('id');
                $productId = $products->count() === 1 ? (int) $products->first() : null;
                if ($productId === null) {
                    $stats['unmatched']++;
                } else {
                    $stats['matched']++;
                }

                $effective = $this->date($row['effective_date'] ?? null);
                $existing = FgMapping::query()->where('fg_code', $code)->first();

                if ($existing === null) {
                    FgMapping::create([
                        'fg_code' => $code, 'item_name' => $name, 'effective_date' => $effective,
                        'source' => 'import', 'imported_at' => Carbon::now(), 'product_id' => $productId,
                    ]);
                    $stats['created']++;

                    continue;
                }

                $changed = $existing->item_name !== $name
                    || ($effective !== null && $existing->effective_date?->toDateString() !== $effective);

                if (! $changed) {
                    $stats['unchanged']++;

                    continue;
                }

                if ($existing->item_name !== $name) {
                    $existing->item_name_previous = $existing->item_name;
                    $existing->item_name = $name;
                }
                if ($effective !== null) {
                    $existing->effective_date = Carbon::parse($effective);
                }
                $existing->imported_at = Carbon::now();
                $existing->save();
                $stats['updated']++;
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($run);
        }

        return $stats;
    }

    /** @return list<array<string, mixed>> */
    private function readRows(string $path): array
    {
        $data = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        if ($data === []) {
            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', (string) $h))), array_shift($data));
        $rows = [];
        foreach ($data as $line) {
            $row = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $row[$key] = $line[$i] ?? null;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString()
                : Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
