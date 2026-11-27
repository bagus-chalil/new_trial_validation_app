<?php

namespace Tests\Feature;

use App\Models\MasterLine;
use App\Models\User;
use App\Services\MachineMaster\MachineLineDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MachineMasterLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // In production the Machine Master lives in another app's DB; in tests it is a table on the default connection.
        config(['database.machine_master_connection' => config('database.default')]);
        Schema::dropIfExists('mc_lines');
        Schema::create('mc_lines', function (Blueprint $table) {
            $table->id();
            $table->string('line_code', 30);
            $table->string('category', 40);
            $table->string('name', 200);
            $table->string('area', 120)->nullable();
            $table->string('status', 30)->default('Active');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable();
        });
        foreach ([['SC-01', 'Skincare', 'Jar Auto'], ['LC-01', 'Make Up', 'Lipcream Line 1'], ['HP-01', 'Make Up', 'Hot Pouring Line 1'], ['LS-01', 'Make Up', 'Lipstick Line 1']] as [$code, $cat, $name]) {
            \DB::table('mc_lines')->insert(['line_code' => $code, 'category' => $cat, 'name' => $name]);
        }
        \DB::table('mc_lines')->insert(['line_code' => 'SC-99', 'category' => 'Skincare', 'name' => 'Gone', 'deleted_at' => now()]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('mc_lines');
        parent::tearDown();
    }

    private function payload(array $over = []): array
    {
        return array_merge(['category' => 'Fillpack', 'area' => 'Skincare', 'code' => 'SC 01', 'name' => 'Skincare 01', 'is_active' => true], $over);
    }

    public function test_a_master_line_can_link_to_an_existing_machine_master_line(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('master-lines.store'), $this->payload(['machine_line_code' => 'SC-01']))->assertRedirect();

        $this->assertSame('SC-01', MasterLine::first()->machine_line_code);
    }

    public function test_an_unknown_or_deleted_machine_line_code_is_rejected(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('master-lines.store'), $this->payload(['machine_line_code' => 'ZZ-01']))
            ->assertSessionHasErrors('machine_line_code');
        $this->actingAs($user)->post(route('master-lines.store'), $this->payload(['machine_line_code' => 'SC-99']))
            ->assertSessionHasErrors('machine_line_code');
        $this->assertSame(0, MasterLine::count());
    }

    public function test_the_link_is_optional(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('master-lines.store'), $this->payload(['machine_line_code' => '']))->assertRedirect();

        $this->assertNull(MasterLine::first()->machine_line_code);
    }

    public function test_the_index_offers_only_active_machine_master_lines(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('master-lines.index'))->assertInertia(fn ($page) => $page
            ->has('machineLines', 4)
            ->where('machineLines.0.code', 'HP-01'));
    }

    public function test_an_unreachable_machine_master_degrades_to_nothing(): void
    {
        config(['database.machine_master_connection' => 'does_not_exist']);

        $this->assertSame([], MachineLineDirectory::options());
        $this->assertFalse(MachineLineDirectory::exists('SC-01'));
    }

    public function test_suggest_normalises_ipc_codes_to_machine_master_codes(): void
    {
        $known = ['SC-01', 'SC-12', 'LC-01', 'HP-01', 'LS-01', 'LP-01', 'EL-01', 'MU-01'];

        $this->assertSame('SC-01', MachineLineDirectory::suggest('SC 01', $known));
        $this->assertSame('SC-12', MachineLineDirectory::suggest('SC 12', $known));
        $this->assertSame('LC-01', MachineLineDirectory::suggest('LC 1', $known));
        $this->assertSame('LS-01', MachineLineDirectory::suggest('LS 1', $known));
        $this->assertSame('HP-01', MachineLineDirectory::suggest('HP', $known));
        $this->assertSame('LP-01', MachineLineDirectory::suggest('LP', $known));
        $this->assertSame('EL-01', MachineLineDirectory::suggest('Eyeliner', $known));
        $this->assertNull(MachineLineDirectory::suggest('MU 01', $known), 'Packing lines are not in the machine master');
        $this->assertNull(MachineLineDirectory::suggest('SC 20', $known), 'unknown code must not be guessed');
        $this->assertNull(MachineLineDirectory::suggest('MC (auto)', $known));
    }

    public function test_the_map_command_links_only_unlinked_lines_and_never_overwrites(): void
    {
        MasterLine::create(['category' => 'Fillpack', 'area' => 'S', 'code' => 'SC 01', 'name' => 'a', 'is_active' => true]);
        MasterLine::create(['category' => 'Filling', 'area' => 'M', 'code' => 'LC 1', 'name' => 'b', 'is_active' => true]);
        MasterLine::create(['category' => 'Packing', 'area' => 'M', 'code' => 'MU 01', 'name' => 'c', 'is_active' => true]);
        MasterLine::create(['category' => 'Fillpack', 'area' => 'S', 'code' => 'LS 1', 'machine_line_code' => 'HP-01', 'name' => 'd', 'is_active' => true]);

        $this->artisan('lines:map-machine-master')->assertSuccessful();
        $this->assertNull(MasterLine::where('code', 'SC 01')->first()->machine_line_code, 'dry run writes nothing');

        $this->artisan('lines:map-machine-master', ['--apply' => true])->assertSuccessful();

        $this->assertSame('SC-01', MasterLine::where('code', 'SC 01')->first()->machine_line_code);
        $this->assertSame('LC-01', MasterLine::where('code', 'LC 1')->first()->machine_line_code);
        $this->assertNull(MasterLine::where('code', 'MU 01')->first()->machine_line_code);
        $this->assertSame('HP-01', MasterLine::where('code', 'LS 1')->first()->machine_line_code, 'existing link kept');
    }
}
