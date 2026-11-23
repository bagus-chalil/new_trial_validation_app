<?php

namespace App\Services\Vision;

use App\Models\IpcClientEvent;
use Illuminate\Support\Facades\Cache;

/**
 * "Analisis AI" for the field-issue log. Sends anonymised FACTS about the open issues (no user
 * names, no form values, no photos, only message/page/count/device facts) as kind `ipc_client_issues`
 * through InsightClient; the prompt lives in the Vision Service, not here. Advisory text only:
 * it never changes data and the issue list itself never depends on it.
 */
class ClientIssueAnalyzer
{
    private const MAX_ISSUES = 10;

    private const CACHE_SECONDS = 600;

    /** The Vision Service refuses a fact longer than this (insights.MAX_FACT_CHARS). */
    private const MAX_FACT_CHARS = 600;

    /** @return array{ok: bool, text?: string, error?: string, cached?: bool} */
    public function analyze(int $days = 7): array
    {
        $facts = $this->facts($days);

        if ($facts === null) {
            return ['ok' => false, 'error' => 'Belum ada kendala terbuka pada rentang ini untuk dianalisis.'];
        }

        $key = 'ipc-issue-ai:'.sha1(implode("\n", $facts));
        if (($cached = Cache::get($key)) !== null) {
            return ['ok' => true, 'text' => $cached, 'cached' => true];
        }

        $result = app(InsightClient::class)->summarize(InsightClient::KIND_IPC_CLIENT_ISSUES, $facts);

        if (! $result['ok']) {
            return ['ok' => false, 'error' => match ($result['code']) {
                InsightClient::ERR_UNREACHABLE => 'Vision Service tidak terjangkau dari server ini.',
                InsightClient::ERR_OUTDATED => 'Vision Service di server belum diperbarui (endpoint teks belum ada).',
                default => 'Analisis AI gagal (Ollama/Vision Service tidak memberi jawaban).',
            }];
        }

        Cache::put($key, $result['text'], self::CACHE_SECONDS);

        return ['ok' => true, 'text' => $result['text']];
    }

    /**
     * Anonymised facts of the top open issues (a header line, then one fact per issue), or null when
     * there is nothing open.
     *
     * @return list<string>|null
     */
    public function facts(int $days = 7): ?array
    {
        $since = now()->subDays($days);

        $rows = IpcClientEvent::query()
            ->whereNull('resolved_at')
            ->where('occurred_at', '>=', $since)
            ->selectRaw('fingerprint, COUNT(*) AS events, COUNT(DISTINCT user_id) AS users, MAX(id) AS last_id, MAX(occurred_at) AS last_seen')
            ->groupBy('fingerprint')
            ->orderByDesc('events')
            ->limit(self::MAX_ISSUES)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $latest = IpcClientEvent::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('fingerprint');

        $spread = IpcClientEvent::query()
            ->whereNull('resolved_at')
            ->where('occurred_at', '>=', $since)
            ->whereIn('fingerprint', $rows->pluck('fingerprint'))
            ->selectRaw('fingerprint, device, browser, os, SUM(online = 0) AS offline_events, COUNT(*) AS c')
            ->groupBy('fingerprint', 'device', 'browser', 'os')
            ->orderByDesc('c')
            ->get()
            ->groupBy('fingerprint');

        $facts = ["Kendala terbuka {$days} hari terakhir (urut dari paling sering)."];
        foreach ($rows as $i => $row) {
            $last = $latest->get($row->fingerprint);
            $devices = collect($spread->get($row->fingerprint, []))->take(4)
                ->map(fn ($s) => "{$s->device} / {$s->browser} / {$s->os}: {$s->c}x".($s->offline_events > 0 ? " ({$s->offline_events}x offline)" : ''))
                ->implode('; ');

            $fact = sprintf(
                '%d. [%s] "%s" | halaman: %s | %d kejadian, %d user | koneksi terakhir: %s | perangkat: %s%s',
                $i + 1,
                $last?->kind,
                mb_substr((string) $last?->message, 0, 200),
                preg_replace('/\d+/', '{n}', (string) parse_url((string) $last?->url, PHP_URL_PATH)),
                $row->events,
                $row->users,
                $last?->net_type ?? 'tidak diketahui',
                $devices ?: '-',
                $last?->detail ? ' | detail: '.mb_substr(preg_replace('/\s+/', ' ', $last->detail), 0, 200) : '',
            );

            $facts[] = mb_substr($fact, 0, self::MAX_FACT_CHARS);
        }

        return $facts;
    }
}
