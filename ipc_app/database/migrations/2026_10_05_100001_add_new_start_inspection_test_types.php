<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New Start Inspection test types requested by the user 2026-10-05: Attribute gets Shrink, Body
 * Label and Bottom Label; Function Test gets Pump Test. Inserted here (not only in
 * MasterDataSeeder) so the production DB gets them on deploy. Only tops up a master list that's
 * already populated — a fresh/empty install gets the full set from MasterDataSeeder instead — and
 * skips any name that already exists, e.g. one an admin added through the Master Test Type screen.
 */
return new class extends Migration
{
    private const TYPES = [
        'SHRINK' => 'Attribute',
        'BODY_LABEL' => 'Attribute',
        'BOTTOM_LABEL' => 'Attribute',
        'PUMP_TEST' => 'Functional',
    ];

    public function up(): void
    {
        if (! DB::table('master_test_types')->exists()) {
            return;
        }

        foreach (self::TYPES as $name => $category) {
            if (DB::table('master_test_types')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('master_test_types')->insert([
                'name' => $name,
                'category' => $category,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('master_test_types')
            ->whereIn('name', array_keys(self::TYPES))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('startup_inspection_test_results')
                ->whereColumn('startup_inspection_test_results.master_test_type_id', 'master_test_types.id'))
            ->delete();
    }
};
