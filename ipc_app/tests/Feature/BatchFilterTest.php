<?php

namespace Tests\Feature;

use App\Models\FinishedCheck;
use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BatchFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(string $noBatch, array $overrides = [], ?User $creator = null, ?MasterLine $line = null): IpcBatch
    {
        $product = MasterProduct::firstOrCreate(['fg_code' => 'FG-1'], ['product_name' => 'Cleansing Oil', 'is_active' => true]);
        $line ??= MasterLine::firstOrCreate(['code' => 'MU 01'], ['category' => 'Packing', 'area' => 'Make Up', 'name' => 'Make Up 01', 'is_active' => true]);
        $creator ??= User::factory()->create();

        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $batch = IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => $noBatch,
            'master_line_id' => $line->id,
            'created_by' => $creator->id,
            'current_stage' => IpcBatch::STAGE_STARTUP,
            'flow_type' => IpcBatch::FLOW_FULL,
            ...$overrides,
        ]);
        $batch->forceFill(['created_at' => $createdAt])->save();

        return $batch;
    }

    private function noBatches(AssertableInertia $page): array
    {
        return collect($page->toArray()['props']['batches']['data'])->pluck('no_batch')->sort()->values()->all();
    }

    public function test_batch_list_filters_by_created_date_range(): void
    {
        $this->makeBatch('OLD-1', ['created_at' => '2026-09-01 10:00:00']);
        $this->makeBatch('MID-1', ['created_at' => '2026-10-10 23:30:00']);
        $this->makeBatch('NEW-1', ['created_at' => '2026-10-20 08:00:00']);

        // The end day is inclusive up to 23:59:59, not cut at midnight.
        $this->actingAs(User::factory()->create())
            ->get('/batches?date_from=2026-10-01&date_to=2026-10-10')
            ->assertInertia(fn (AssertableInertia $page) => $this->assertSame(['MID-1'], $this->noBatches($page)));
    }

    public function test_batch_list_filters_by_creator_line_and_flow(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $lineB = MasterLine::create(['code' => 'MU 02', 'category' => 'Packing', 'area' => 'Make Up', 'name' => 'Make Up 02', 'is_active' => true]);

        $this->makeBatch('A-FULL', creator: $alice);
        $this->makeBatch('B-FILLING', ['flow_type' => IpcBatch::FLOW_FILLING], creator: $bob);
        $this->makeBatch('B-LINE2', creator: $bob, line: $lineB);

        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get('/batches?created_by='.$bob->id)
            ->assertInertia(fn ($page) => $this->assertSame(['B-FILLING', 'B-LINE2'], $this->noBatches($page)));
        $this->actingAs($viewer)->get('/batches?master_line_id='.$lineB->id)
            ->assertInertia(fn ($page) => $this->assertSame(['B-LINE2'], $this->noBatches($page)));
        $this->actingAs($viewer)->get('/batches?flow=filling')
            ->assertInertia(fn ($page) => $this->assertSame(['B-FILLING'], $this->noBatches($page)));
    }

    public function test_filters_combine_with_search_and_stage(): void
    {
        $this->makeBatch('26X001/AAA', ['created_at' => '2026-10-10 09:00:00']);
        $this->makeBatch('26X002/BBB', ['created_at' => '2026-09-01 09:00:00']);
        $this->makeBatch('26X003/CCC', ['created_at' => '2026-10-11 09:00:00', 'current_stage' => IpcBatch::STAGE_FILLING]);

        $this->actingAs(User::factory()->create())
            ->get('/batches?q=26X&date_from=2026-10-01&stage=startup')
            ->assertInertia(function ($page) {
                $this->assertSame(['26X001/AAA'], $this->noBatches($page));
                $this->assertSame(1, $page->toArray()['props']['batches']['total']);
            });
    }

    public function test_bad_filter_values_are_ignored_not_errors(): void
    {
        $this->makeBatch('ONLY-1');

        $this->actingAs(User::factory()->create())
            ->get('/batches?date_from=not-a-date&date_to=2026-13-45&created_by=abc&master_line_id[]=1&flow=bogus')
            ->assertOk()
            ->assertInertia(function ($page) {
                $this->assertSame(['ONLY-1'], $this->noBatches($page));
                $this->assertSame([], array_diff_key($page->toArray()['props']['filters'], ['q' => 1, 'stage' => 1]));
            });
    }

    public function test_a_reversed_date_range_is_swapped(): void
    {
        $this->makeBatch('IN-RANGE', ['created_at' => '2026-10-05 09:00:00']);

        $this->actingAs(User::factory()->create())
            ->get('/batches?date_from=2026-10-10&date_to=2026-10-01')
            ->assertInertia(fn ($page) => $this->assertSame(['IN-RANGE'], $this->noBatches($page)));
    }

    public function test_filter_options_list_lines_and_staff_creators_only(): void
    {
        $this->makeBatch('X-1');
        $approver = User::factory()->approver()->create();

        $this->actingAs(User::factory()->create())->get('/batches')
            ->assertInertia(function ($page) use ($approver) {
                $options = $page->toArray()['props']['filterOptions'];
                $this->assertSame(['Make Up 01'], collect($options['lines'])->pluck('name')->all());
                $this->assertNotContains($approver->id, collect($options['creators'])->pluck('id')->all());
                $this->assertSame(['Filling Saja', 'Fillpack', 'Packing + FG'], collect($options['flows'])->pluck('label')->sort()->values()->all());
            });
    }

    public function test_micro_check_queue_filters_by_flow_date_search_and_status(): void
    {
        $micro = User::factory()->microbiologist()->create();
        $saved = fn (IpcBatch $b) => FinishedCheck::create(['ipc_batch_id' => $b->id, 'user_id' => $b->created_by, 'save_count' => 1, 'completed_at' => now()]);

        $a = $this->makeBatch('MC-FULL', ['created_at' => '2026-10-10 09:00:00']);
        $b = $this->makeBatch('MC-PACK', ['flow_type' => IpcBatch::FLOW_PACKING_FG, 'created_at' => '2026-09-01 09:00:00']);
        $saved($a);
        $saved($b);

        $get = fn (string $query) => $this->actingAs($micro)->get('/micro-checks'.$query);

        $get('?flow=packing_fg')->assertInertia(fn ($p) => $this->assertSame(['MC-PACK'], $this->noBatches($p)));
        $get('?date_from=2026-10-01')->assertInertia(fn ($p) => $this->assertSame(['MC-FULL'], $this->noBatches($p)));
        $get('?q=PACK')->assertInertia(fn ($p) => $this->assertSame(['MC-PACK'], $this->noBatches($p)));
        $get('?q=Cleansing')->assertInertia(fn ($p) => $this->assertSame(['MC-FULL', 'MC-PACK'], $this->noBatches($p)));
        $get('?status=tersimpan')->assertInertia(fn ($p) => $this->assertSame([], $this->noBatches($p)));
        $get('?status=belum')->assertInertia(fn ($p) => $this->assertSame(['MC-FULL', 'MC-PACK'], $this->noBatches($p)));
        // Filling-only can never be in this queue, so it is not an accepted flow here.
        $get('?flow=filling')->assertInertia(fn ($p) => $this->assertSame(['MC-FULL', 'MC-PACK'], $this->noBatches($p)));
    }

    public function test_approval_queue_accepts_filters_and_search(): void
    {
        $approver = User::factory()->approver()->create();

        $this->actingAs($approver)->get('/approvals?date_from=2026-10-01&q=zzz&flow=full')
            ->assertOk()
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];
                $this->assertSame('zzz', $props['filters']['q']);
                $this->assertSame('2026-10-01', $props['filters']['date_from']);
                $this->assertSame('full', $props['filters']['flow']);
                $this->assertSame(0, $props['queue']['total']);
                $this->assertArrayHasKey('lines', $props['filterOptions']);
            });
    }
}
