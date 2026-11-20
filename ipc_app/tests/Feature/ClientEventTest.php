<?php

namespace Tests\Feature;

use App\Models\IpcClientEvent;
use App\Models\User;
use App\Support\ClientEventRecorder;
use App\Support\UserAgentSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientEventTest extends TestCase
{
    use RefreshDatabase;

    private const REDMI_UA = 'Mozilla/5.0 (Linux; U; Android 13; Redmi Note 11 Build/TKQ1.221114.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/110.0.5481.154 Mobile Safari/537.36 XiaoMi/MiuiBrowser/14.2.0';

    /** @return array<string, mixed> */
    private function event(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'camera',
            'message' => 'Kamera tidak bisa dibuka (NotAllowedError)',
            'url' => '/batches/12/packing-check',
            'online' => true,
        ], $overrides);
    }

    public function test_guest_can_post_events_and_device_facts_are_parsed_from_the_user_agent(): void
    {
        $this->withHeader('User-Agent', self::REDMI_UA)
            ->postJson('/client-log', ['events' => [$this->event()]])
            ->assertOk();

        $row = IpcClientEvent::first();
        $this->assertSame('camera', $row->kind);
        $this->assertSame('Redmi Note 11', $row->device);
        $this->assertSame('Mi Browser 14', $row->browser);
        $this->assertSame('Android 13', $row->os);
        $this->assertSame(12, $row->ipc_batch_id);
        $this->assertNull($row->user_id);
    }

    public function test_signed_in_user_is_attached(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/client-log', ['events' => [$this->event()]])->assertOk();

        $this->assertSame($user->id, IpcClientEvent::first()->user_id);
    }

    public function test_same_problem_on_different_batches_shares_one_fingerprint(): void
    {
        $a = ClientEventRecorder::fingerprint('slow_request', 'Request lebih dari 8 detik: PUT /batches/12/x', '/batches/12/x');
        $b = ClientEventRecorder::fingerprint('slow_request', 'Request lebih dari 8 detik: PUT /batches/99/x', '/batches/99/x');
        $c = ClientEventRecorder::fingerprint('camera', 'Request lebih dari 8 detik: PUT /batches/99/x', '/batches/99/x');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    public function test_unknown_kind_message_length_and_batch_size_are_bounded(): void
    {
        $this->postJson('/client-log', ['events' => [$this->event(['kind' => 'weird', 'message' => str_repeat('x', 2000)])]])->assertOk();

        $row = IpcClientEvent::first();
        $this->assertSame('js_error', $row->kind);
        $this->assertSame(500, mb_strlen($row->message));

        $this->postJson('/client-log', ['events' => array_fill(0, 21, $this->event())])->assertStatus(422);
        $this->postJson('/client-log', ['events' => []])->assertStatus(422);
    }

    public function test_offline_queued_event_keeps_its_real_time_but_never_a_future_one(): void
    {
        $past = now()->subHours(3)->toIso8601String();
        $future = now()->addDay()->toIso8601String();

        $this->postJson('/client-log', ['events' => [
            $this->event(['message' => 'old one', 'occurred_at' => $past]),
            $this->event(['message' => 'future one', 'occurred_at' => $future]),
        ]])->assertOk();

        $old = IpcClientEvent::where('message', 'old one')->first();
        $this->assertTrue($old->occurred_at->diffInMinutes(now()->subHours(3), true) < 1);
        $this->assertTrue(IpcClientEvent::where('message', 'future one')->first()->occurred_at->lte(now()->addMinute()));
    }

    public function test_user_agent_parser_handles_common_cases(): void
    {
        $chrome = UserAgentSummary::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36');
        $this->assertSame(['browser' => 'Chrome 120', 'os' => 'Windows', 'device' => 'Komputer'], $chrome);

        $huawei = UserAgentSummary::parse('Mozilla/5.0 (Linux; Android 12; AGS5-W09) AppleWebKit/537.36 Chrome/106.0.0.0 Safari/537.36');
        $this->assertSame('AGS5-W09', $huawei['device']);
        $this->assertSame('Chrome 106', $huawei['browser']);
    }

    public function test_only_admin_can_open_the_log_and_it_groups_by_issue(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach (range(1, 3) as $i) {
            ClientEventRecorder::record($this->event(['url' => "/batches/{$i}/packing-check"]), request(), $staff->id);
        }
        ClientEventRecorder::record($this->event(['kind' => 'js_error', 'message' => 'x is undefined']), request(), $staff->id);

        $this->actingAs($staff)->get(route('client-events.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('client-events.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client-events/index')
                ->has('issues.data', 2)
                ->where('summary.open_issues', 2)
                ->where('issues.data.0.events', fn ($n) => in_array($n, [1, 3], true)));
    }

    public function test_resolving_hides_an_issue_until_it_happens_again(): void
    {
        $admin = User::factory()->admin()->create();
        $first = ClientEventRecorder::record($this->event(), request());

        $this->actingAs($admin)->post(route('client-events.resolve'), ['fingerprint' => $first->fingerprint])->assertRedirect();
        $this->assertNotNull($first->fresh()->resolved_at);

        $this->actingAs($admin)->get(route('client-events.index'))
            ->assertInertia(fn (Assert $page) => $page->has('issues.data', 0));

        ClientEventRecorder::record($this->event(), request());

        $this->actingAs($admin)->get(route('client-events.index'))
            ->assertInertia(fn (Assert $page) => $page->has('issues.data', 1)->where('issues.data.0.open_events', 1));
    }

    public function test_admin_gets_the_open_issue_badge_count_and_staff_does_not(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();
        ClientEventRecorder::record($this->event(), request());

        $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('openClientIssues', 1));
        $this->actingAs($staff)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('openClientIssues', 0));
    }

    public function test_unhandled_server_exceptions_are_recorded(): void
    {
        ClientEventRecorder::recordServerError(new \RuntimeException('boom 42'));

        $row = IpcClientEvent::first();
        $this->assertSame('server_error', $row->kind);
        $this->assertSame('RuntimeException: boom 42', $row->message);
    }

    public function test_ai_analysis_is_off_by_default_and_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();

        $this->actingAs($admin)->postJson(route('client-events.analyze'))->assertNotFound();

        config(['vision.issue_ai.enabled' => true]);
        $this->actingAs($staff)->postJson(route('client-events.analyze'))->assertForbidden();
    }

    public function test_ai_analysis_sends_an_anonymised_digest_and_returns_the_answer(): void
    {
        config(['vision.issue_ai.enabled' => true, 'vision.base_url' => 'http://vision.test']);
        Http::fake(['vision.test/api/insights/summarize' => Http::response(['status' => 'OK', 'kind' => 'ipc_client_issues', 'text' => 'Pola: kamera di Redmi.'])]);

        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create(['name' => 'Budi Rahasia', 'email' => 'budi.rahasia@example.test']);
        $redmi = Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => self::REDMI_UA]);
        ClientEventRecorder::record($this->event(['detail' => 'secret-detail']), $redmi, $staff->id);

        $this->actingAs($admin)->postJson(route('client-events.analyze'))
            ->assertOk()
            ->assertJson(['ok' => true, 'text' => 'Pola: kamera di Redmi.']);

        Http::assertSent(function (HttpRequest $request) {
            $data = $request->data();
            $facts = implode("\n", $data['facts'] ?? []);

            return $request->url() === 'http://vision.test/api/insights/summarize'
                && $data['kind'] === 'ipc_client_issues'
                && is_array($data['facts'])
                // the prompt now lives in the Vision Service: only kind + facts travel
                && array_keys($data) === ['kind', 'facts']
                && ! isset($data['system'], $data['prompt'])
                && str_contains($facts, 'Redmi Note 11')
                && str_contains($facts, '/batches/{n}/packing-check')
                && ! str_contains($facts, 'Budi')
                && ! str_contains($facts, 'example.test');
        });
    }

    private function adminWithOpenIssue(): User
    {
        config(['vision.issue_ai.enabled' => true, 'vision.base_url' => 'http://vision.test']);
        ClientEventRecorder::record($this->event(), request());

        return User::factory()->admin()->create();
    }

    public function test_ai_analysis_with_nothing_open_sends_nothing(): void
    {
        config(['vision.issue_ai.enabled' => true, 'vision.base_url' => 'http://vision.test']);
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())->postJson(route('client-events.analyze'))->assertOk()->assertJson(['ok' => false]);

        Http::assertNothingSent();
    }

    public function test_ai_analysis_explains_an_outdated_vision_service(): void
    {
        $admin = $this->adminWithOpenIssue();
        Http::fake(['*' => Http::response('nope', 404)]);

        $this->actingAs($admin)->postJson(route('client-events.analyze'))
            ->assertJson(['ok' => false, 'error' => 'Vision Service di server belum diperbarui (endpoint teks belum ada).']);
    }

    public function test_ai_analysis_explains_an_unreachable_vision_service(): void
    {
        $admin = $this->adminWithOpenIssue();
        Http::fake(['*' => fn () => throw new ConnectionException('down')]);

        $this->actingAs($admin)->postJson(route('client-events.analyze'))
            ->assertJson(['ok' => false, 'error' => 'Vision Service tidak terjangkau dari server ini.']);
    }

    public function test_ai_analysis_handles_an_ollama_error(): void
    {
        $admin = $this->adminWithOpenIssue();
        Http::fake(['*' => Http::response(['status' => 'ERROR', 'error' => 'x'], 503)]);

        $this->actingAs($admin)->postJson(route('client-events.analyze'))->assertJson(['ok' => false]);
    }
}
