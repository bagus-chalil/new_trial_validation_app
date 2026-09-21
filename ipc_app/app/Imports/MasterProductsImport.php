<?php

namespace App\Imports;

use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Upserts master_products by fg_code and, when a row also carries a bulk_code,
 * upserts that product's master_product_bulk_codes by (product, bulk_code).
 * A row with a blank bulk_code only touches the product — no_batch is optional
 * on the bulk code itself, matching the master-data screen's own validation.
 */
class MasterProductsImport implements ToCollection, WithHeadingRow
{
    public int $productsCreated = 0;

    public int $productsUpdated = 0;

    public int $bulkCodesCreated = 0;

    public int $bulkCodesUpdated = 0;

    /** @var list<string> */
    public array $rowErrors = [];

    /** @var array<int, true> product ids already counted this run, so a product repeated across
     *  several rows (one per bulk code) is only counted once in the created/updated summary. */
    private array $touchedProductIds = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for 0-index, +1 for the heading row

            $fgCode = trim((string) ($row['fg_code'] ?? ''));
            $productName = trim((string) ($row['nama_produk'] ?? ''));
            $bulkCode = trim((string) ($row['bulk_code'] ?? ''));
            $noBatch = trim((string) ($row['no_batch'] ?? ''));

            if ($fgCode === '' && $productName === '' && $bulkCode === '') {
                continue;
            }

            $validator = Validator::make(
                ['fg_code' => $fgCode, 'nama_produk' => $productName, 'bulk_code' => $bulkCode, 'no_batch' => $noBatch],
                [
                    'fg_code' => ['required', 'string', 'max:100'],
                    'nama_produk' => ['required', 'string', 'max:150'],
                    'bulk_code' => ['nullable', 'string', 'max:100'],
                    'no_batch' => ['nullable', 'string', 'max:100'],
                ],
            );

            if ($validator->fails()) {
                $this->rowErrors[] = "Baris {$rowNumber}: {$validator->errors()->first()}";

                continue;
            }

            $product = MasterProduct::where('fg_code', $fgCode)->first();
            $isNewProduct = $product === null;
            $product ??= new MasterProduct(['fg_code' => $fgCode]);
            $product->product_name = $productName;
            $product->is_active = $this->parseActive($row['status_produk'] ?? null);
            $product->save();

            if (! isset($this->touchedProductIds[$product->id])) {
                $this->touchedProductIds[$product->id] = true;
                $isNewProduct ? $this->productsCreated++ : $this->productsUpdated++;
            }

            if ($bulkCode === '') {
                continue;
            }

            $bulkCodeRow = MasterProductBulkCode::where('master_product_id', $product->id)
                ->where('bulk_code', $bulkCode)
                ->first();
            $isNewBulkCode = $bulkCodeRow === null;
            $bulkCodeRow ??= new MasterProductBulkCode([
                'master_product_id' => $product->id,
                'bulk_code' => $bulkCode,
            ]);
            $bulkCodeRow->no_batch = $noBatch !== '' ? $noBatch : null;
            $bulkCodeRow->is_active = $this->parseActive($row['status_bulk_code'] ?? null);
            $bulkCodeRow->save();

            $isNewBulkCode ? $this->bulkCodesCreated++ : $this->bulkCodesUpdated++;
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
        return sprintf(
            '%d produk baru, %d produk diperbarui. %d bulk code baru, %d bulk code diperbarui.',
            $this->productsCreated,
            $this->productsUpdated,
            $this->bulkCodesCreated,
            $this->bulkCodesUpdated,
        );
    }

    public function hasChanges(): bool
    {
        return $this->productsCreated + $this->productsUpdated + $this->bulkCodesCreated + $this->bulkCodesUpdated > 0;
    }
}
