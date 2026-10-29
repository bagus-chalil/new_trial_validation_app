<?php

namespace App\Services\TrialStatus;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads the per-Finish-Good-code trial roll-up from new_trial_validation_app's internal API
 * (GET /api/internal/trial-status). Never throws: the report must still open when the other app
 * is down, so a failure comes back as `ok = false` (with the last good data, if any) and is not cached.
 */
class TrialStatusClient
{
    public const CACHE_KEY = 'trial-status:by-fg-code';

    /** Last good response, kept long so a brief outage still shows data (flagged unavailable). */
    public const STALE_KEY = 'trial-status:by-fg-code:last-good';

    /**
     * @return array{ok: bool, data: array<string, array<string, mixed>>}
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return ['ok' => true, 'data' => $cached];
        }

        $token = (string) config('services.trial_app.token');

        if ($token === '') {
            return $this->unavailable();
        }

        try {
            $response = Http::baseUrl((string) config('services.trial_app.url'))
                ->withHeaders(['X-Internal-Token' => $token])
                ->acceptJson()
                ->timeout((int) config('services.trial_app.timeout'))
                ->retry(1, 200, throw: false)
                ->get('/api/internal/trial-status');
        } catch (Throwable) {
            return $this->unavailable();
        }

        $data = $response->json('data');

        if (! $response->successful() || ! is_array($data)) {
            return $this->unavailable();
        }

        Cache::put(self::CACHE_KEY, $data, (int) config('services.trial_app.cache_seconds'));
        Cache::put(self::STALE_KEY, $data, now()->addDay());

        return ['ok' => true, 'data' => $data];
    }

    /** @return array{ok: bool, data: array<string, array<string, mixed>>} */
    private function unavailable(): array
    {
        $stale = Cache::get(self::STALE_KEY);

        return ['ok' => false, 'data' => is_array($stale) ? $stale : []];
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
