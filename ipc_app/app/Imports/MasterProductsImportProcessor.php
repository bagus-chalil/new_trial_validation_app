<?php

namespace App\Imports;

use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;

/**
 * Master Produk import: one row per (FG Code, Nama Produk, Bulk Code[, Shelf Life]).
 *
 * - master_products is keyed by fg_code (unique, case-insensitive under MySQL's collation), so a
 *   product can only hold one name: the first row for an FG Code sets it, later rows for the same
 *   FG Code with a different name are still imported for their bulk code.
 * - Only a row identical in FG Code + Nama Produk + Bulk Code to an earlier row is skipped.
 * - Shelf Life is optional; a blank cell never clears an existing value.
 * - A soft-deleted product/bulk code matching a row is restored (fg_code / (product, bulk_code)
 *   uniqueness includes trashed rows, so a fresh insert would fail anyway).
 */
class MasterProductsImportProcessor implements MasterImportProcessor
{
    private const SHELF_LIFE_MIN = 1;

    private const SHELF_LIFE_MAX = 120;

    public function label(): string
    {
        return 'Master Produk';
    }

    public function requiredHeadings(): array
    {
        return ['fg_code' => 'FG Code', 'nama_produk' => 'Nama Produk'];
    }

    public function columns(): array
    {
        return ['fg_code' => 'FG Code', 'nama_produk' => 'Nama Produk', 'bulk_code' => 'Bulk Code', 'shelf_life' => 'Shelf Life (Bulan)'];
    }

    public function plan(array $rows, callable $progress): ImportPlan
    {
        $plan = new ImportPlan;
        $plan->totalRows = count($rows);

        /** @var array<string, array{fg_code: string, name: string, row: int, shelf_life: ?int, shelf_life_row: ?int, bulk_codes: array<string, string>}> $groups */
        $groups = [];
        $seenRows = [];

        foreach ($rows as $i => ['row' => $rowNumber, 'data' => $data]) {
            $values = [
                'fg_code' => $data['fg_code'] ?? '',
                'nama_produk' => $data['nama_produk'] ?? '',
                'bulk_code' => $data['bulk_code'] ?? '',
                'shelf_life' => $data['shelf_life_bulan'] ?? $data['shelf_life'] ?? '',
            ];

            $errors = $this->validate($values);
            if ($errors !== []) {
                $plan->error($rowNumber, implode(' ', $errors), $values);
                $this->tick($progress, $i, $plan->totalRows);

                continue;
            }

            $fgKey = mb_strtolower($values['fg_code']);
            $rowKey = $fgKey."\0".$values['nama_produk']."\0".mb_strtolower($values['bulk_code']);
            if (isset($seenRows[$rowKey])) {
                $plan->warning($rowNumber, "Duplikat identik dengan baris {$seenRows[$rowKey]}, dilewati.", $values);
                $this->tick($progress, $i, $plan->totalRows);

                continue;
            }
            $seenRows[$rowKey] = $rowNumber;

            $groups[$fgKey] ??= [
                'fg_code' => $values['fg_code'],
                'name' => $values['nama_produk'],
                'row' => $rowNumber,
                'shelf_life' => null,
                'shelf_life_row' => null,
                'bulk_codes' => [],
            ];

            if ($values['shelf_life'] !== '') {
                $shelfLife = (int) $values['shelf_life'];
                $group = &$groups[$fgKey];
                if ($group['shelf_life'] === null) {
                    $group['shelf_life'] = $shelfLife;
                    $group['shelf_life_row'] = $rowNumber;
                } elseif ($group['shelf_life'] !== $shelfLife) {
                    $plan->warning(
                        $rowNumber,
                        "Shelf Life {$shelfLife} bulan berbeda dengan baris {$group['shelf_life_row']} ({$group['shelf_life']} bulan). Yang dipakai nilai baris {$group['shelf_life_row']}.",
                        $values,
                    );
                }
                unset($group);
            }

            if ($values['bulk_code'] !== '') {
                $groups[$fgKey]['bulk_codes'][mb_strtolower($values['bulk_code'])] ??= $values['bulk_code'];
            }

            $plan->validRows++;
            $this->tick($progress, $i, $plan->totalRows);
        }

        $this->resolveAgainstDatabase($plan, $groups);
        $progress($plan->totalRows, $plan->totalRows);

        return $plan;
    }

    public function apply(ImportPlan $plan, callable $progress): array
    {
        $result = array_fill_keys(array_keys($plan->preview), 0);
        $result['skipped_duplicates'] = $plan->preview['skipped_duplicates'] ?? 0;
        $total = count($plan->operations);
        $done = 0;
        $now = now();

        foreach ($plan->operations as $op) {
            $product = $op['product_id'] !== null
                ? MasterProduct::withTrashed()->find($op['product_id'])
                : new MasterProduct(['fg_code' => $op['fg_code'], 'is_active' => true]);

            if ($op['action'] === 'restored') {
                $product->deleted_at = null;
                $product->deleted_by = null;
            }
            $product->product_name = $op['name'];
            if ($op['shelf_life'] !== null) {
                $product->shelf_life_months = $op['shelf_life'];
            }
            if ($product->isDirty() || ! $product->exists) {
                $product->save();
            }
            $result['products_'.$op['action']]++;

            foreach ($op['bulk_codes'] as $bulk) {
                $this->applyBulkCode($product->id, $bulk, $now);
                $result['bulk_codes_'.$bulk['action']]++;
            }

            if (++$done % 200 === 0) {
                $progress($done, $total);
            }
        }
        $progress($total, $total);

        return $result;
    }

    /** @param array{action: string, bulk_code: string, id: ?int} $bulk */
    private function applyBulkCode(int $productId, array $bulk, mixed $now): void
    {
        if ($bulk['action'] === 'new') {
            MasterProductBulkCode::insert([
                'master_product_id' => $productId,
                'bulk_code' => $bulk['bulk_code'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ($bulk['action'] === 'restored') {
            MasterProductBulkCode::withTrashed()->whereKey($bulk['id'])->update(['deleted_at' => null, 'deleted_by' => null, 'updated_at' => $now]);
        }
    }

    /** @return list<string> */
    private function validate(array $values): array
    {
        $errors = [];

        if ($values['fg_code'] === '') {
            $errors[] = 'FG Code wajib diisi.';
        } elseif (mb_strlen($values['fg_code']) > 100) {
            $errors[] = 'FG Code maksimal 100 karakter.';
        }

        if ($values['nama_produk'] === '') {
            $errors[] = 'Nama Produk wajib diisi.';
        } elseif (mb_strlen($values['nama_produk']) > 150) {
            $errors[] = 'Nama Produk maksimal 150 karakter (saat ini '.mb_strlen($values['nama_produk']).').';
        }

        if (mb_strlen($values['bulk_code']) > 100) {
            $errors[] = 'Bulk Code maksimal 100 karakter.';
        }

        $shelfLife = $values['shelf_life'];
        if ($shelfLife !== '' && (! ctype_digit($shelfLife) || (int) $shelfLife < self::SHELF_LIFE_MIN || (int) $shelfLife > self::SHELF_LIFE_MAX)) {
            $errors[] = 'Shelf Life harus bilangan bulat '.self::SHELF_LIFE_MIN.'-'.self::SHELF_LIFE_MAX." (bulan), bukan \"{$shelfLife}\".";
        }

        return $errors;
    }

    /** @param array<string, array{fg_code: string, name: string, row: int, shelf_life: ?int, shelf_life_row: ?int, bulk_codes: array<string, string>}> $groups */
    private function resolveAgainstDatabase(ImportPlan $plan, array $groups): void
    {
        $existing = MasterProduct::withTrashed()
            ->get(['id', 'fg_code', 'product_name', 'shelf_life_months', 'deleted_at'])
            ->keyBy(fn (MasterProduct $product) => mb_strtolower($product->fg_code));

        $existingBulk = MasterProductBulkCode::withTrashed()
            ->get(['id', 'master_product_id', 'bulk_code', 'deleted_at'])
            ->keyBy(fn (MasterProductBulkCode $bulk) => $bulk->master_product_id."\0".mb_strtolower($bulk->bulk_code));

        $preview = [
            'products_new' => 0,
            'products_updated' => 0,
            'products_restored' => 0,
            'products_unchanged' => 0,
            'bulk_codes_new' => 0,
            'bulk_codes_restored' => 0,
            'bulk_codes_existing' => 0,
        ];

        foreach ($groups as $fgKey => $group) {
            $product = $existing->get($fgKey);

            if ($product === null) {
                $action = 'new';
            } elseif ($product->deleted_at !== null) {
                $action = 'restored';
                $plan->warning($group['row'], "FG Code {$product->fg_code} ada di Recycle Bin dan akan dipulihkan.", ['fg_code' => $group['fg_code'], 'nama_produk' => $group['name'], 'bulk_code' => '', 'shelf_life' => '']);
            } elseif ($product->product_name !== $group['name'] || ($group['shelf_life'] !== null && $product->shelf_life_months !== $group['shelf_life'])) {
                $action = 'updated';
            } else {
                $action = 'unchanged';
            }

            $bulkOps = [];
            foreach ($group['bulk_codes'] as $bulkKey => $bulkCode) {
                $existingRow = $product !== null ? $existingBulk->get($product->id."\0".$bulkKey) : null;
                $bulkAction = match (true) {
                    $existingRow === null => 'new',
                    $existingRow->deleted_at !== null => 'restored',
                    default => 'existing',
                };
                $preview['bulk_codes_'.$bulkAction]++;
                $bulkOps[] = ['action' => $bulkAction, 'bulk_code' => $bulkCode, 'id' => $existingRow?->id];
            }

            $preview['products_'.$action]++;
            $plan->operations[] = [
                'action' => $action,
                'product_id' => $product?->id,
                'fg_code' => $group['fg_code'],
                'name' => $group['name'],
                'shelf_life' => $group['shelf_life'],
                'bulk_codes' => $bulkOps,
            ];
        }

        $preview['skipped_duplicates'] = $plan->totalRows - $plan->validRows - $plan->errorRows();
        $plan->preview = $preview;
    }

    private function tick(callable $progress, int $index, int $total): void
    {
        if (($index + 1) % 250 === 0) {
            $progress($index + 1, $total);
        }
    }
}
