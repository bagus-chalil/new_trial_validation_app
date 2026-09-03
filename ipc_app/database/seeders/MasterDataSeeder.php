<?php

namespace Database\Seeders;

use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterTestType;
use Illuminate\Database\Seeder;

/**
 * Placeholder master data so batches/startup checks are actually creatable in dev.
 * `master_lines`/`master_products` rows are still illustrative examples, not the real master
 * lists (those live in SharePoint, not in the Power Apps export) — replace with real rows, or
 * build the admin CRUD screens to manage these, before this app goes anywhere near production.
 * `master_test_types` IS the real, complete set of 15 test-type flags wired to Start_Inspection
 * buttons in the Power Apps export 2026-09-02 (ipc_app/app_legacy/, Controls/1544.json).
 * Category groupings (Leakage/Functional/Attribute) were a guess at that point — **confirmed
 * 2026-09-03 against a real Start_Inspection screenshot from the user**: Leakage = VACCUM,
 * TORSI, PRESS_TEST, DROP_TEST_P, DROP_TEST_S (5); Functional = SPRAY, FLIP_TOP, RUB_TEST,
 * SWING_TEST, TAPE_TEST, HARDESS_TEST (6); Attribute unchanged (4). TORSI/DROP_TEST_P/
 * DROP_TEST_S moved from Functional to Leakage accordingly. Uses updateOrCreate (not
 * firstOrCreate) specifically so re-running this seeder corrects any row already seeded with
 * the old, wrong category.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        MasterLine::firstOrCreate(
            ['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01'],
            ['name' => 'Make Up 01', 'is_active' => true],
        );
        MasterLine::firstOrCreate(
            ['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 02'],
            ['name' => 'Make Up 02', 'is_active' => true],
        );
        MasterLine::firstOrCreate(
            ['category' => 'Filling', 'area' => 'Filling Room', 'code' => 'FL 01'],
            ['name' => 'Filling 01', 'is_active' => true],
        );

        MasterProduct::firstOrCreate(
            ['fg_code' => 'FG-0001'],
            ['product_name' => 'Sample Product A', 'bulk_code' => 'BULK-0001', 'is_active' => true],
        );
        MasterProduct::firstOrCreate(
            ['fg_code' => 'FG-0002'],
            ['product_name' => 'Sample Product B', 'bulk_code' => 'BULK-0002', 'is_active' => true],
        );

        $testTypes = [
            'VACCUM' => MasterTestType::CATEGORY_LEAKAGE,
            'TORSI' => MasterTestType::CATEGORY_LEAKAGE,
            'PRESS_TEST' => MasterTestType::CATEGORY_LEAKAGE,
            'DROP_TEST_P' => MasterTestType::CATEGORY_LEAKAGE,
            'DROP_TEST_S' => MasterTestType::CATEGORY_LEAKAGE,
            'SPRAY' => MasterTestType::CATEGORY_FUNCTIONAL,
            'FLIP_TOP' => MasterTestType::CATEGORY_FUNCTIONAL,
            'RUB_TEST' => MasterTestType::CATEGORY_FUNCTIONAL,
            'SWING_TEST' => MasterTestType::CATEGORY_FUNCTIONAL,
            'TAPE_TEST' => MasterTestType::CATEGORY_FUNCTIONAL,
            'HARDESS_TEST' => MasterTestType::CATEGORY_FUNCTIONAL,
            'SECURITY_SEAL' => MasterTestType::CATEGORY_ATTRIBUTE,
            'SHADE_LABEL' => MasterTestType::CATEGORY_ATTRIBUTE,
            'QR_CODE' => MasterTestType::CATEGORY_ATTRIBUTE,
            'HOLOGRAM' => MasterTestType::CATEGORY_ATTRIBUTE,
        ];

        foreach ($testTypes as $name => $category) {
            MasterTestType::updateOrCreate(['name' => $name], ['category' => $category, 'is_active' => true]);
        }
    }
}
