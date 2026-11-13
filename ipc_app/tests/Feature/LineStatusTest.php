<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineStatusTest extends TestCase
{
    use RefreshDatabase;

    private function line(array $overrides = []): MasterLine
    {
        return MasterLine::create(array_merge([
            'category' => 'Filling', 'area' => 'A1', 'code' => 'L-01', 'name' => 'Line 01', 'is_active' => true,
        ], $overrides));
    }

    private function batch(MasterLine $line, string $stage, User $creator): IpcBatch
    {
        $product = MasterProduct::create(['fg_code' => '602016001', 'product_name' => 'Moko Cushion', 'is_active' => true]);

        return IpcBatch::create([
            'master_product_id' => $product->id,
            'no_batch' => 'B-100',
            'master_line_id' => $line->id,
            'created_by' => $creator->id,
            'current_stage' => $stage,
            'flow_type' => IpcBatch::FLOW_FULL,
        ]);
    }

    public function test_new_lines_get_an_unguessable_public_token(): void
    {
        $line = $this->line();

        $this->assertSame(32, strlen($line->public_token));
        $this->assertNotSame($this->line(['code' => 'L-02'])->public_token, $line->public_token);
    }

    public function test_public_page_needs_no_login_and_shows_batch_product_fg_and_pic(): void
    {
        $line = $this->line();
        $user = User::factory()->create(['name' => 'Budi Operator']);
        $this->batch($line, IpcBatch::STAGE_STARTUP, $user);

        $this->get($line->publicUrl())
            ->assertOk()
            ->assertSee('Line 01')
            ->assertSee('B-100')
            ->assertSee('Moko Cushion')
            ->assertSee('602016001')
            ->assertSee('Budi Operator')
            ->assertSee('Startup Check');
    }

    public function test_public_page_shows_idle_when_nothing_is_running(): void
    {
        $line = $this->line();
        $user = User::factory()->create();
        $this->batch($line, IpcBatch::STAGE_COMPLETED, $user);

        $this->get($line->publicUrl())->assertOk()->assertSee('Line idle');
    }

    public function test_unknown_or_inactive_line_token_is_404(): void
    {
        $this->get('/line/'.str_repeat('a', 32))->assertNotFound();

        $line = $this->line(['is_active' => false]);
        $this->get($line->publicUrl())->assertNotFound();
    }

    public function test_monitor_requires_login_and_lists_active_lines(): void
    {
        $line = $this->line();
        $user = User::factory()->create();
        $this->batch($line, IpcBatch::STAGE_FILLING, $user);

        $this->get('/monitor-line')->assertRedirect('/login');

        $this->actingAs($user)->get('/monitor-line')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('line-monitor/index')
                ->has('lines', 1)
                ->where('lines.0.batches.0.no_batch', 'B-100')
                ->where('lines.0.batches.0.stage', IpcBatch::STAGE_FILLING));
    }

    public function test_qr_poster_requires_login(): void
    {
        $line = $this->line();

        $this->get(route('master-lines.qr', $line))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route('master-lines.qr', $line))->assertOk()->assertSee('<svg', false);
    }
}
