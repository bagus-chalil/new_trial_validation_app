<?php

use App\Models\MachineLine;
use App\Models\Plant;

function csvFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'plants').'.csv';
    file_put_contents($path, $content);

    return $path;
}

test('plant import creates plants, fills location and keeps blank cells untouched', function () {
    $a = MachineLine::create(['line_code' => 'SC-01', 'category' => 'Skincare', 'name' => 'A', 'status' => 'Active', 'area' => 'Old Room']);
    $b = MachineLine::create(['line_code' => 'SC-02', 'category' => 'Skincare', 'name' => 'B', 'status' => 'Active']);
    $file = csvFile("Line ID,Plant Code,Plant Name,Building,Floor,Area\nSC-01,P1,Plant Satu,Gedung A,2,\nSC-02,P1,,Gedung A,1,Room 5\n");

    $this->artisan('machines:import-plants', ['file' => $file, '--dry-run' => true])->assertSuccessful();
    expect(Plant::count())->toBe(0);

    $this->artisan('machines:import-plants', ['file' => $file])->assertSuccessful();

    $plant = Plant::where('code', 'P1')->firstOrFail();
    expect($plant->name)->toBe('Plant Satu');
    $a->refresh();
    $b->refresh();
    expect($a->plant_id)->toBe($plant->id)->and($a->building)->toBe('Gedung A')->and($a->floor)->toBe('2')->and($a->area)->toBe('Old Room')
        ->and($b->plant_id)->toBe($plant->id)->and($b->area)->toBe('Room 5');

    // idempotent
    $this->artisan('machines:import-plants', ['file' => $file])->assertSuccessful();
    expect(Plant::count())->toBe(1);
});

test('plant import writes nothing when a row is invalid', function () {
    MachineLine::create(['line_code' => 'SC-01', 'category' => 'Skincare', 'name' => 'A', 'status' => 'Active']);
    $file = csvFile("Line ID,Plant Code,Plant Name,Building,Floor,Area\nSC-01,P1,Plant Satu,G,1,\nXX-99,P1,,G,1,\n");

    $this->artisan('machines:import-plants', ['file' => $file])->assertFailed();

    expect(Plant::count())->toBe(0)->and(MachineLine::first()->building)->toBeNull();
});

test('plant template lists every current line', function () {
    MachineLine::create(['line_code' => 'SC-01', 'category' => 'Skincare', 'name' => 'A', 'status' => 'Active']);
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.csv';

    $this->artisan('machines:import-plants', ['file' => $path, '--template' => true])->assertSuccessful();

    expect(file_get_contents($path))->toContain('"Line ID","Plant Code"')->toContain('SC-01');
});
