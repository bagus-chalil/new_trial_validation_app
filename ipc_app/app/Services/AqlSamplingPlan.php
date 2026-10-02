<?php

namespace App\Services;

/**
 * "Tabel Accepted Quality Level (AQL) Single Sampling Plans for Normal Inspection" — the paper
 * table posted at the QC Finished Good station. Finished Check derives its Quantity Sampling AQL
 * (+ CD/MD/mD = AQL 0.0 Critical / 1.5 Major / 2.5 Minor accept numbers) and Quantity Special
 * Inspection from Quantity WI (the lot size) through this table; Special Inspection's CD/MD/mD
 * are always 0. Mirrored client-side in resources/js/lib/aql-sampling-plan.ts — keep both in sync.
 */
class AqlSamplingPlan
{
    /**
     * [lot size from, sample size, special inspection, critical, major, minor], ascending.
     *
     * @var list<array{int, int, int, int, int, int}>
     */
    public const TABLE = [
        [91, 20, 3, 0, 1, 1],
        [151, 32, 3, 0, 1, 2],
        [281, 50, 3, 0, 2, 3],
        [501, 80, 5, 0, 3, 5],
        [1201, 125, 5, 0, 5, 7],
        [3201, 200, 5, 0, 7, 10],
        [10001, 315, 5, 0, 10, 14],
        [35001, 500, 8, 0, 14, 21],
        [150001, 800, 8, 0, 21, 21],
        [500001, 1250, 8, 0, 21, 21],
    ];

    /**
     * "Apabila jumlah sample yang harus diperiksa kurang dari 91pcs maka dilakukan pengecekan
     * 100%" — below the table's first row every piece is inspected, with zero defects accepted.
     */
    public const FULL_INSPECTION_BELOW = 91;

    /**
     * @return array<string, int>|null the derived Finished Check fields, or null for lot <= 0
     */
    public static function forLotSize(int $lotSize): ?array
    {
        if ($lotSize <= 0) {
            return null;
        }

        if ($lotSize < self::FULL_INSPECTION_BELOW) {
            [$sample, $special, $critical, $major, $minor] = [$lotSize, min(3, $lotSize), 0, 0, 0];
        } else {
            $row = collect(self::TABLE)->last(fn (array $row) => $lotSize >= $row[0]);
            [, $sample, $special, $critical, $major, $minor] = $row;
        }

        return [
            'quantity_sampling_aql' => $sample,
            'quantity_sample_aql_cd' => $critical,
            'quantity_sample_aql_md' => $major,
            'quantity_sample_aql_mnd' => $minor,
            'quantity_special_inspection' => $special,
            'quantity_special_inspection_cd' => 0,
            'quantity_special_inspection_md' => 0,
            'quantity_special_inspection_mnd' => 0,
        ];
    }
}
