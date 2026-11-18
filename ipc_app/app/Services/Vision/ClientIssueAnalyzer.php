<?php

namespace App\Services\Vision;

use App\Models\IpcClientEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * "Analisis AI" for the field-issue log. Sends an anonymised digest of the open issues (no user
 * names, no form values, no photos, only message/page/count/device facts) to the Vision Service's
 * text endpoint, i.e. the same route to the Ollama GPU server the OCR reads use. Advisory text only:
 * it never changes data and the issue list itself never depends on it.
 */
class ClientIssueAnalyzer
{
    private const MAX_ISSUES = 10;

    private const CACHE_SECONDS = 600;

    private const SYSTEM = <<<'TXT'
Kamu membantu admin aplikasi pemeriksaan kualitas pabrik kosmetik (IPC) membaca log kendala dari tablet/HP operator.
Jawab dalam Bahasa Indonesia, singkat dan konkret (maksimal sekitar 250 kata), dengan format:
1. Ringkasan: satu-dua kalimat tentang pola utama.
2. Per kendala penting (urut dari paling mendesak): dugaan penyebab dan satu langkah cek yang bisa dilakukan admin.
Aturan: gunakan hanya data yang diberikan, jangan mengarang penyebab yang tidak didukung data. Jika data belum cukup, katakan apa yang perlu dikumpulkan.
Petunjuk: banyak kejadian dari satu perangkat/browser saja biasanya masalah perangkat atau browser lama. Kejadian dengan status offline atau koneksi 2g/slow-2g biasanya jaringan. Kejadian di semua perangkat biasanya bug aplikasi atau server.
TXT;

    /** @return array{ok: bool, text?: string, error?: string, cached?: bool} */
    public function analyze(int $days = 7): array
    {
        $digest = $this->digest($days);

        if ($digest === null) {
            return ['ok' => false, 'error' => 'Belum ada kendala terbuka pada rentang ini untuk dianalisis.'];
        }

        $key = 'ipc-issue-ai:'.sha1($digest);
        if (($cached = Cache::get($key)) !== null) {
            return ['ok' => true, 'text' => $cached, 'cached' => true];
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('vision.base_url'), '/'))
                ->timeout((int) config('vision.llm_timeout'))
                ->connectTimeout((int) config('vision.connect_timeout'))
                ->acceptJson()
                ->post('/api/summarize-text', ['system' => self::SYSTEM, 'prompt' => $digest]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => 'Vision Service tidak terjangkau dari server ini.'];
        }

        if ($response->status() === 404 || $response->status() === 405) {
            return ['ok' => false, 'error' => 'Vision Service di server belum diperbarui (endpoint teks belum ada).'];
        }
        if (! $response->successful() || ! is_string($response->json('text')) || trim($response->json('text')) === '') {
            return ['ok' => false, 'error' => 'Analisis AI gagal (Ollama/Vision Service tidak memberi jawaban).'];
        }

        $text = trim($response->json('text'));
        Cache::put($key, $text, self::CACHE_SECONDS);

        return ['ok' => true, 'text' => $text];
    }

    /** Anonymised text table of the top open issues, or null when there is nothing open. */
    public function digest(int $days = 7): ?string
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

        $lines = ["Kendala terbuka {$days} hari terakhir (urut dari paling sering):"];
        foreach ($rows as $i => $row) {
            $last = $latest->get($row->fingerprint);
            $devices = collect($spread->get($row->fingerprint, []))->take(4)
                ->map(fn ($s) => "{$s->device} / {$s->browser} / {$s->os}: {$s->c}x".($s->offline_events > 0 ? " ({$s->offline_events}x offline)" : ''))
                ->implode('; ');

            $lines[] = sprintf(
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
        }

        return implode("\n", $lines);
    }
}
