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
 * `master_test_types`, however, IS the real, complete set of 15 test-type flags wired to
 * Start_Inspection buttons in the Power Apps export 2026-09-02 (ipc_app/app_legacy/,
 * Controls/1544.json) — category groupings (Leakage/Functional/Attribute) are still a
 * reasonable guess, not confirmed by the export. See ipc_app/CLAUDE.md.
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
            'PRESS_TEST' => MasterTestType::CATEGORY_LEAKAGE,
            'TORSI' => MasterTestType::CATEGORY_FUNCTIONAL,
            'DROP_TEST_P' => MasterTestType::CATEGORY_FUNCTIONAL,
            'DROP_TEST_S' => MasterTestType::CATEGORY_FUNCTIONAL,
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
            MasterTestType::firstOrCreate(['name' => $name], ['category' => $category, 'is_active' => true]);
        }
    }
}
