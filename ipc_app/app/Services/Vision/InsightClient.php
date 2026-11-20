<?php

namespace App\Services\Vision;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Consumer side of the Vision Service's typed text insights (`POST /api/insights/summarize`).
 *
 * The Vision Service is the AI provider: it owns the prompt, the writing rules and the output check
 * (it refuses an answer that recommends approve/reject/hold). This app only sends a `kind` and the
 * facts it computed itself, and gets a paragraph back. Returns a result array instead of throwing, so
 * callers can always fall back to their rule-based text.
 */
class InsightClient
{
    public const KIND_IPC_BATCH_SUMMARY = 'ipc_batch_summary';

    /**
     * @param  list<string>  $facts  plain, already-anonymised sentences
     * @return array{ok: true, text: string}|array{ok: false, error: string}
     */
    public function summarize(string $kind, array $facts): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('vision.base_url'), '/'))
                ->timeout((int) config('vision.llm_timeout'))
                ->connectTimeout((int) config('vision.connect_timeout'))
                ->acceptJson()
                ->post('/api/insights/summarize', ['kind' => $kind, 'facts' => array_values($facts)]);
        } catch (ConnectionException) {
            return ['ok' => false, 'error' => 'Vision Service tidak terjangkau dari server ini.'];
        }

        $text = $response->json('text');

        return match (true) {
            $response->status() === 404 || $response->status() === 405 => ['ok' => false, 'error' => 'Vision Service di server belum diperbarui (endpoint insight belum ada).'],
            $response->status() === 422 && $response->json('status') === 'ERROR' => ['ok' => false, 'error' => 'Ringkasan AI ditolak Vision Service (melanggar aturan advisory). Pakai poin ringkasan di atas.'],
            ! $response->successful() || ! is_string($text) || trim($text) === '' => ['ok' => false, 'error' => 'Ringkasan AI gagal (Ollama/Vision Service tidak memberi jawaban).'],
            default => ['ok' => true, 'text' => trim($text)],
        };
    }
}
