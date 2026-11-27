<?php

use App\Models\Equipment;
use App\Models\MachineLine;
use App\Models\Plant;
use App\Models\User;
use App\Services\Machines\MachineMasterImporter;

function lineData(array $over = []): array
{
    return array_merge([
        'category' => 'Skincare',
        'name' => 'Tube 9',
        'status' => 'Active',
    ], $over);
}

test('viewer cannot open the machine master', function () {
    $viewer = User::factory()->create(['role' => 'Viewer']);

    $this->actingAs($viewer)->get(route('admin.machines.index'))->assertForbidden();
});

test('staff can list lines and filter by category', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    MachineLine::create(['line_code' => 'SC-01', 'category' => 'Skincare', 'name' => 'Jar Auto', 'status' => 'Active']);
    MachineLine::create(['line_code' => 'MX-SC01', 'category' => 'Mixing', 'name' => 'Mixer', 'status' => 'Active']);

    $this->actingAs($staff)
        ->get(route('admin.machines.index', ['category' => 'Mixing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/machines/index')
            ->has('lines.data', 1)
            ->where('lines.data.0.line_code', 'MX-SC01'));
});

test('a new line gets a generated unique code and a supplied code is kept', function () {
    $staff = User::factory()->create(['role' => 'Staff']);

    $this->actingAs($staff)->post(route('admin.machines.store'), lineData())->assertRedirect();
    $this->actingAs($staff)->post(route('admin.machines.store'), lineData(['name' => 'Tube 10']))->assertRedirect();
    $this->actingAs($staff)->post(route('admin.machines.store'), lineData(['line_code' => 'sc-77', 'name' => 'Custom']))->assertRedirect();

    expect(MachineLine::orderBy('id')->pluck('line_code')->all())->toBe(['SC-01', 'SC-02', 'SC-77']);
});

test('a duplicate supplied line code is rejected', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    MachineLine::create(['line_code' => 'SC-01', 'category' => 'Skincare', 'name' => 'x', 'status' => 'Active']);

    $this->actingAs($staff)
        ->post(route('admin.machines.store'), lineData(['line_code' => 'SC-01']))
        ->assertSessionHasErrors('line_code');
});

test('updating a line never changes its code', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $line = MachineLine::create(['line_code' => 'SC-05', 'category' => 'Skincare', 'name' => 'Old', 'status' => 'Active']);

    $this->actingAs($staff)->post(route('admin.machines.store'), lineData([
        'id' => $line->id, 'line_code' => 'ZZ-99', 'name' => 'New name',
    ]))->assertRedirect();

    $line->refresh();
    expect($line->line_code)->toBe('SC-05')->and($line->name)->toBe('New name');
});

test('line can be tied to a plant and location', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $plant = Plant::create(['code' => 'P1', 'name' => 'Plant Cikarang']);

    $this->actingAs($staff)->post(route('admin.machines.store'), lineData([
        'plant_id' => $plant->id, 'building' => 'Gedung A', 'floor' => '1', 'area' => 'Skincare 1st Floor',
    ]))->assertRedirect();

    $line = MachineLine::first();
    expect($line->plant_id)->toBe($plant->id)->and($line->building)->toBe('Gedung A');
});

test('equipment codes follow the line code and are never reused', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $line = MachineLine::create(['line_code' => 'SC-01', 'category' => 'Skincare', 'name' => 'Jar', 'status' => 'Active']);

    foreach (['Filler', 'Capper'] as $name) {
        $this->actingAs($staff)->post(route('admin.machines.equipment.store', $line), ['name' => $name])->assertRedirect();
    }
    $first = Equipment::where('name', 'Filler')->first();
    expect($first->equipment_code)->toBe('SC-01-E01')->and($first->process_step)->toBe(1);

    $this->actingAs($staff)->delete(route('admin.machines.equipment.destroy', $first))->assertRedirect();
    $this->actingAs($staff)->post(route('admin.machines.equipment.store', $line), ['name' => 'Labeler'])->assertRedirect();

    expect(Equipment::withTrashed()->orderBy('id')->pluck('equipment_code')->all())->toBe(['SC-01-E01', 'SC-01-E02', 'SC-01-E03']);
});

test('a plant that is still used by a line cannot be deleted', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $plant = Plant::create(['code' => 'P1', 'name' => 'Plant']);
    MachineLine::create(['line_code' => 'SC-01', 'category' => 'Skincare', 'name' => 'Jar', 'status' => 'Active', 'plant_id' => $plant->id]);

    $this->actingAs($staff)->delete(route('admin.machines.plants.destroy', $plant))->assertSessionHasErrors('plant');
    expect(Plant::count())->toBe(1);
});

test('importer reads the maintainers workbook idempotently', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension not enabled.');
    }
    $file = base_path('../docs/Cosmax Line Specification Database 2026.xlsx');
    if (! is_file($file)) {
        $this->markTestSkipped('Workbook not present.');
    }

    $importer = app(MachineMasterImporter::class);
    $plan = $importer->plan($file);
    expect($plan['warnings'])->toBe([])->and($plan['lines'])->toHaveCount(66)->and($plan['equipment'])->toHaveCount(97);

    $importer->apply($plan);
    $second = $importer->apply($plan);

    expect(MachineLine::count())->toBe(66)->and(Equipment::count())->toBe(97)
        ->and($second['lines_created'])->toBe(0)->and($second['equipment_created'])->toBe(0);
});
