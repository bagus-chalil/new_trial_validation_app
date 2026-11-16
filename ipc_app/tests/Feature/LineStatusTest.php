<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use App\Models\IpcSetting;
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
        $product = MasterProduct::firstOrCreate(['fg_code' => '602016001'], ['product_name' => 'Moko Cushion', 'is_active' => true]);

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

    public function test_batches_in_approval_or_completed_are_not_shown(): void
    {
        $line = $this->line();
        $user = User::factory()->create();
        $this->batch($line, IpcBatch::STAGE_APPROVAL, $user);
        $this->batch($line, IpcBatch::STAGE_COMPLETED, $user);

        $this->get($line->publicUrl())->assertOk()->assertSee('Line idle')->assertDontSee('B-100');
        $this->actingAs($user)->get('/monitor-line')
            ->assertInertia(fn ($page) => $page->has('lines.0.batches', 0));
    }

    public function test_stage_track_only_has_line_stages(): void
    {
        $line = $this->line();
        $user = User::factory()->create();
        $this->batch($line, IpcBatch::STAGE_FINISHED, $user);

        $this->actingAs($user)->get('/monitor-line')
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.batches.0.stages', ['startup', 'filling', 'packing', 'finished'])
                ->where('lines.0.batches.0.step', 4));
    }

    public function test_public_board_needs_no_login_with_the_board_token(): void
    {
        $line = $this->line();
        $user = User::factory()->create();
        $this->batch($line, IpcBatch::STAGE_FILLING, $user);

        $url = route('line-monitor.public', IpcSetting::lineMonitorToken());

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('line-monitor/index')
                ->where('publicMode', true)
                ->where('lines.0.batches.0.no_batch', 'B-100')
                // per-line tokens are never handed out on the public board
                ->where('lines.0.public_url', null)
                ->missing('share'));

        $this->get('/monitor/'.str_repeat('x', 40))->assertNotFound();
    }

    public function test_monitor_offers_the_share_link_and_qr(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/monitor-line')
            ->assertInertia(fn ($page) => $page
                ->where('share.url', route('line-monitor.public', IpcSetting::lineMonitorToken()))
                ->where('share.qr_svg', fn ($svg) => str_contains($svg, '<svg')));

        $this->actingAs($user)->get(route('line-monitor.qr'))->assertOk()->assertSee('Monitor Line')->assertSee('<svg', false);
    }

    public function test_only_admin_can_regenerate_the_board_token_and_the_old_link_dies(): void
    {
        $old = IpcSetting::lineMonitorToken();

        $this->actingAs(User::factory()->create())
            ->post(route('line-monitor.regenerate-token'))
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $this->actingAs($admin)->post(route('line-monitor.regenerate-token'))->assertRedirect();

        $this->assertNotSame($old, IpcSetting::lineMonitorToken());
        auth()->logout();
        $this->get(route('line-monitor.public', $old))->assertNotFound();
        $this->get(route('line-monitor.public', IpcSetting::lineMonitorToken()))->assertOk();
    }

    public function test_master_line_list_gives_share_info_only_for_active_lines(): void
    {
        $active = $this->line();
        $inactive = $this->line(['code' => 'L-02', 'name' => 'Line 02', 'is_active' => false]);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)->get(route('master-lines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('masters/lines/index')
                ->where('lines.data', function ($rows) use ($active, $inactive) {
                    $byId = collect($rows)->keyBy('id');
                    $a = $byId[$active->id]['share'] ?? null;

                    return $a !== null
                        && $a['url'] === $active->publicUrl()
                        && str_contains($a['qr_svg'], '<svg')
                        && $a['poster_url'] === route('master-lines.qr', $active)
                        && ! isset($byId[$inactive->id]['share']);
                }));
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
