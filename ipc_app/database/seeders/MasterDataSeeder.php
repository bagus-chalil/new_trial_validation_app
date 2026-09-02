<?php

namespace Database\Seeders;

use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterTestType;
use Illuminate\Database\Seeder;

/**
 * Placeholder master data so batches/startup checks are actually creatable in dev.
 * Not sourced from the real Power Apps master lists (poppler unavailable to read the
 * source PDF's screenshots) — replace with real rows, or build the admin CRUD screens
 * to manage these, before this app goes anywhere near production. See ipc_app/CLAUDE.md.
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

        MasterTestType::firstOrCreate(['name' => 'VACCUM'], ['category' => MasterTestType::CATEGORY_LEAKAGE, 'is_active' => true]);
        MasterTestType::firstOrCreate(['name' => 'TORSI'], ['category' => MasterTestType::CATEGORY_FUNCTIONAL, 'is_active' => true]);
        MasterTestType::firstOrCreate(['name' => 'SPRAY'], ['category' => MasterTestType::CATEGORY_FUNCTIONAL, 'is_active' => true]);
    }
}
