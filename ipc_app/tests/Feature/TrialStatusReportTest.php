<?php

namespace Tests\Feature;

use App\Models\MasterProduct;
use App\Models\User;
use App\Services\TrialStatus\TrialStatusClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrialStatusReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.trial_app.token' => 'secret-token', 'services.trial_app.url' => 'http://trial.test']);
        Cache::forget(TrialStatusClient::CACHE_KEY);
        Cache::forget(TrialStatusClient::STALE_KEY);
    }

    /** @param  array<string, mixed>  $overrides */
    private function trialRow(array $overrides = []): array
    {
        return array_merge([
            'status' => 'approved',
            'product_name' => 'Trial Side Name',
            'trial_count' => 1,
            'approved_count' => 1,
            'latest_trial_id' => 1,
            'latest_trial_code' => 'TRIAL-1',
            'latest_progress_status' => 'Approved',
            'latest_step' => 'Approval',
            'pending_with' => null,
            'last_approved_at' => '2026-10-01T09:00:00+07:00',
            'latest_updated_at' => now()->toIso8601String(),
        ], $overrides);
    }

    private function fakeTrialApi(array $data): void
    {
        Http::fake(['trial.test/api/internal/trial-status' => Http::response(['data' => $data])]);
    }

    public function test_guests_and_non_admins_cannot_open_the_report(): void
    {
        $this->get('/masters/trial-status')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]))
            ->get('/masters/trial-status')
            ->assertForbidden();
    }

    public function test_it_pivots_on_trial_codes_and_flags_the_ones_missing_from_master_ipc(): void
    {
        MasterProduct::create(['fg_code' => '111', 'product_name' => 'IPC Side Name', 'is_active' => true]);
        MasterProduct::create(['fg_code' => '999', 'product_name' => 'Never Trialled', 'is_active' => true]);
        $this->fakeTrialApi([
            '111' => $this->trialRow(),
            '222' => $this->trialRow(['status' => 'in_progress', 'product_name' => 'Only In Trial App']),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/masters/trial-status')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('masters/trial-status/index')
                ->where('trialAppAvailable', true)
                ->where('rows.total', 2)
                ->where('rows.data.0.product_name', 'IPC Side Name')
                ->where('rows.data.0.in_master', true)
                ->where('rows.data.1.product_name', 'Only In Trial App')
                ->where('rows.data.1.in_master', false)
                ->where('counts.missing', 1));
    }

    public function test_status_and_missing_filters_narrow_the_rows(): void
    {
        MasterProduct::create(['fg_code' => '111', 'product_name' => 'Alpha', 'is_active' => true]);
        $this->fakeTrialApi([
            '111' => $this->trialRow(),
            '222' => $this->trialRow(['status' => 'rejected', 'product_name' => 'Bravo']),
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/masters/trial-status?status=rejected')
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.fg_code', '222'));

        $this->actingAs($admin)->get('/masters/trial-status?master=missing')
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.fg_code', '222'));

        $this->actingAs($admin)->get('/masters/trial-status?q=alpha')
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.fg_code', '111'));
    }

    public function test_the_report_still_opens_when_the_trial_app_is_down(): void
    {
        Http::fake(['trial.test/*' => Http::response('boom', 500)]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/masters/trial-status')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('trialAppAvailable', false)->where('rows.total', 0));

        $this->assertNull(Cache::get(TrialStatusClient::CACHE_KEY), 'a failed fetch must not be cached');
    }

    public function test_an_outage_after_a_good_fetch_keeps_showing_the_last_data_but_flags_it(): void
    {
        Http::fake(['trial.test/*' => Http::sequence()
            ->push(['data' => ['111' => $this->trialRow()]])
            ->whenEmpty(Http::response('boom', 500))]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/masters/trial-status')->assertInertia(fn ($page) => $page->where('trialAppAvailable', true));

        Cache::forget(TrialStatusClient::CACHE_KEY); // the short cache expired; the next fetch hits the outage

        $this->actingAs($admin)->get('/masters/trial-status')
            ->assertInertia(fn ($page) => $page->where('trialAppAvailable', false)->where('rows.total', 1));
    }

    public function test_a_missing_token_means_unavailable_without_calling_the_api(): void
    {
        config(['services.trial_app.token' => '']);
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/masters/trial-status')
            ->assertInertia(fn ($page) => $page->where('trialAppAvailable', false));

        Http::assertNothingSent();
    }

    public function test_the_response_is_cached_and_refresh_forces_a_new_fetch(): void
    {
        $this->fakeTrialApi(['111' => $this->trialRow()]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/masters/trial-status');
        $this->actingAs($admin)->get('/masters/trial-status');
        Http::assertSentCount(1);

        $this->actingAs($admin)->get('/masters/trial-status?refresh=1');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Internal-Token', 'secret-token'));
    }

    public function test_stage_and_stale_filters_and_the_stage_breakdown(): void
    {
        $this->fakeTrialApi([
            '111' => $this->trialRow(['status' => 'in_progress', 'latest_progress_status' => 'In Review', 'latest_updated_at' => now()->subDays(20)->toIso8601String()]),
            '222' => $this->trialRow(['status' => 'in_progress', 'latest_progress_status' => 'In Review']),
            '333' => $this->trialRow(['status' => 'in_progress', 'latest_progress_status' => 'Ready for Approval', 'latest_updated_at' => now()->subDays(30)->toIso8601String()]),
            '444' => $this->trialRow(['status' => 'approved', 'latest_updated_at' => now()->subDays(90)->toIso8601String()]),
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/masters/trial-status')
            ->assertInertia(fn ($page) => $page
                ->where('stages', [['name' => 'In Review', 'count' => 2], ['name' => 'Ready for Approval', 'count' => 1]])
                ->where('counts.stale', 2)
                ->where('staleDays', 14));

        $this->actingAs($admin)->get('/masters/trial-status?stage=In+Review')
            ->assertInertia(fn ($page) => $page->where('rows.total', 2));

        // Oldest-idle first, and an approved trial is never "stale" however old.
        $this->actingAs($admin)->get('/masters/trial-status?stale=1')
            ->assertInertia(fn ($page) => $page
                ->where('rows.total', 2)
                ->where('rows.data.0.fg_code', '333')
                ->where('rows.data.0.idle_days', 30)
                ->where('rows.data.1.fg_code', '111'));
    }
}
