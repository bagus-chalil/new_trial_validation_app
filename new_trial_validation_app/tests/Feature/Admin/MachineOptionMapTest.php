<?php

use App\Models\Equipment;
use App\Models\MachineLine;
use App\Models\MachineOptionMap;
use App\Models\MasterOption;
use App\Models\User;
use App\Services\Machines\MachineOptionMatcher;
use Illuminate\Support\Facades\DB;

function mkLine(string $code, string $category, string $name): MachineLine
{
    return MachineLine::create(['line_code' => $code, 'category' => $category, 'name' => $name, 'status' => 'Active']);
}

function seedLines(): array
{
    $l = [];
    foreach ([
        ['SC-04', 'Skincare', '4 Head Filling Machine'],
        ['SC-05', 'Skincare', 'Cushion Auto'],
        ['SC-08', 'Skincare', 'Jireh 1'],
        ['SC-11', 'Skincare', 'Tube 2'],
        ['SC-20', 'Skincare', 'Mascara'],
        ['LC-02', 'Make Up', 'Lipcream Line 2'],
        ['LS-01', 'Make Up', 'Lipstick Line 1'],
        ['MS-01', 'Make Up', 'Mascara Line 1'],
        ['MX-SC05', 'Mixing', 'Skin Care 5 UHM 500L'],
        ['MX-PW01', 'Mixing', 'Powder Henschel 1 300L'],
        ['MX-PW04', 'Mixing', 'Powder Henschel 1 300L'],
        ['PK-01', 'Packing Equipment', 'Packing Equipment Pool'],
    ] as [$code, $cat, $name]) {
        $l[$code] = mkLine($code, $cat, $name);
    }
    $l['inkjet'] = Equipment::create(['equipment_code' => 'PK-01-E15', 'line_id' => $l['PK-01']->id, 'name' => 'Inkjet Printer']);

    return $l;
}

function suggest(string $name): ?array
{
    return (new MachineOptionMatcher(MachineLine::all(), Equipment::all()))->suggest($name);
}

test('matcher resolves explicit naming rules to the right line', function () {
    $l = seedLines();

    expect(suggest('Fill TUBE SC 11')['line_id'])->toBe($l['SC-11']->id)
        ->and(suggest('Fill FOUR HEAD SC4')['line_id'])->toBe($l['SC-04']->id)
        ->and(suggest('Fill CUSHION AUTO LINE 5')['line_id'])->toBe($l['SC-05']->id)
        ->and(suggest('Filling Lipcream 2')['line_id'])->toBe($l['LC-02']->id)
        ->and(suggest('Lipstik 1')['line_id'])->toBe($l['LS-01']->id)
        ->and(suggest('Fill JIREH 1')['line_id'])->toBe($l['SC-08']->id);
});

test('matcher matches mixers exactly and tells duplicate-named Henschel lines apart by number', function () {
    $l = seedLines();

    $mixer = suggest('Skin Care 5 (UHM 500L)');
    expect($mixer['line_id'])->toBe($l['MX-SC05']->id)
        ->and($mixer['score'])->toBeGreaterThanOrEqual(MachineOptionMatcher::AUTO_THRESHOLD);
    expect(suggest('Powder Heinschel 1 (Mixer 300L)')['line_id'])->toBe($l['MX-PW01']->id)
        ->and(suggest('Powder Heinschel 4 (Mixer 300L)')['line_id'])->toBe($l['MX-PW04']->id);
});

test('matcher never auto-confirms the mascara ambiguity or the packing pool', function () {
    $l = seedLines();

    $mascara = suggest('Mascara semi auto filling');
    expect($mascara['line_id'])->toBe($l['MS-01']->id)
        ->and($mascara['score'])->toBeLessThan(MachineOptionMatcher::AUTO_THRESHOLD);

    $ink = suggest('Coding INK JET PRINTER 65 µm');
    expect($ink['line_id'])->toBe($l['PK-01']->id)
        ->and($ink['equipment_id'])->toBe($l['inkjet']->id)
        ->and($ink['score'])->toBeLessThan(MachineOptionMatcher::AUTO_THRESHOLD);
});

test('matcher gives no suggestion for an unrelated name', function () {
    seedLines();

    expect(suggest('Delta Robot'))->toBeNull();
});

test('command dry run writes nothing, apply writes and never overwrites admin decisions', function () {
    $l = seedLines();
    foreach (['Fill TUBE SC 11', 'Mascara semi auto filling', 'Delta Robot', 'Lipstik 1'] as $i => $n) {
        MasterOption::create(['type' => 'machine_used', 'name' => $n, 'sort_order' => $i, 'is_active' => true]);
    }
    MachineOptionMap::create(['option_name' => 'Lipstik 1', 'line_id' => $l['MS-01']->id, 'status' => 'manual']);

    $this->artisan('machines:map-trial-options')->assertSuccessful();
    expect(MachineOptionMap::count())->toBe(1);

    $this->artisan('machines:map-trial-options --apply')->assertSuccessful();

    $maps = MachineOptionMap::pluck('status', 'option_name');
    expect($maps['Fill TUBE SC 11'])->toBe('auto')
        ->and($maps['Mascara semi auto filling'])->toBe('suggested')
        ->and($maps->has('Delta Robot'))->toBeFalse()
        ->and($maps['Lipstik 1'])->toBe('manual');
    expect(MachineOptionMap::where('option_name', 'Lipstik 1')->value('line_id'))->toBe($l['MS-01']->id);
});

test('names still used by trials but missing from master_options are listed too', function () {
    DB::table('trials_header')->insert([
        'trial_code' => 'T-1', 'product_name' => 'P', 'product_type' => 'Tube', 'progress_status' => 'Draft',
        'machine_used' => json_encode(['Old Removed Machine']), 'created_by' => 'a@b.c',
    ]);

    expect(MachineOptionMap::optionNames())->toContain('Old Removed Machine');
});

test('viewer cannot open the mapping screen', function () {
    $viewer = User::factory()->create(['role' => 'Viewer']);

    $this->actingAs($viewer)->get(route('admin.machines.options.index'))->assertForbidden();
});

test('staff can map an option to a line and equipment, mark not applicable, and reset', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $l = seedLines();
    MasterOption::create(['type' => 'machine_used', 'name' => 'Coding INK JET PRINTER 65 µm', 'sort_order' => 1, 'is_active' => true]);
    MasterOption::create(['type' => 'machine_used', 'name' => 'Manual Labeling', 'sort_order' => 2, 'is_active' => true]);

    $this->actingAs($staff)->post(route('admin.machines.options.store'), [
        'option_name' => 'Coding INK JET PRINTER 65 µm',
        'line_id' => $l['PK-01']->id,
        'equipment_id' => $l['inkjet']->id,
    ])->assertRedirect();
    $map = MachineOptionMap::where('option_name', 'Coding INK JET PRINTER 65 µm')->firstOrFail();
    expect($map->status)->toBe('manual')->and($map->mapped_by)->toBe($staff->id);

    $this->actingAs($staff)->post(route('admin.machines.options.store'), [
        'option_name' => 'Manual Labeling', 'not_applicable' => true,
    ])->assertRedirect();
    expect(MachineOptionMap::where('option_name', 'Manual Labeling')->value('status'))->toBe('not_applicable');

    $this->actingAs($staff)->delete(route('admin.machines.options.destroy', $map->id))->assertRedirect();
    expect(MachineOptionMap::where('option_name', 'Coding INK JET PRINTER 65 µm')->exists())->toBeFalse();
});

test('mapping validation rejects unknown option, missing line and equipment of another line', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $l = seedLines();
    MasterOption::create(['type' => 'machine_used', 'name' => 'Real Option', 'sort_order' => 1, 'is_active' => true]);

    $this->actingAs($staff)->post(route('admin.machines.options.store'), ['option_name' => 'Nope', 'line_id' => $l['SC-04']->id])
        ->assertSessionHasErrors('option_name');
    $this->actingAs($staff)->post(route('admin.machines.options.store'), ['option_name' => 'Real Option'])
        ->assertSessionHasErrors('line_id');
    $this->actingAs($staff)->post(route('admin.machines.options.store'), [
        'option_name' => 'Real Option', 'line_id' => $l['SC-04']->id, 'equipment_id' => $l['inkjet']->id,
    ])->assertSessionHasErrors('equipment_id');
});

test('index lists every option with usage and status', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $l = seedLines();
    MasterOption::create(['type' => 'machine_used', 'name' => 'Lipstik 1', 'sort_order' => 1, 'is_active' => true]);
    MachineOptionMap::create(['option_name' => 'Lipstik 1', 'line_id' => $l['LS-01']->id, 'status' => 'auto', 'score' => 0.95]);

    $this->actingAs($staff)->get(route('admin.machines.options.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/machines/options')
            ->has('rows', 1)
            ->where('rows.0.status', 'auto')
            ->where('rows.0.line_label', 'LS-01 - Lipstick Line 1'));
});

test('lineCodesFor resolves a trial machine list through the mapping', function () {
    $l = seedLines();
    MachineOptionMap::create(['option_name' => 'Lipstik 1', 'line_id' => $l['LS-01']->id, 'status' => 'manual']);

    expect(MachineOptionMap::lineCodesFor(['Lipstik 1', 'Unmapped']))->toBe(['LS-01']);
});
