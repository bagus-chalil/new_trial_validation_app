<?php

namespace App\Support;

use App\Models\IpcClientEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

/** Writes field-issue rows. Never throws: logging a problem must not create a new one. */
class ClientEventRecorder
{
    /**
     * @param  array<string, mixed>  $event  kind, message, detail?, url?, online?, net_type?, app_version?, occurred_at?
     */
    public static function record(array $event, ?Request $request = null, ?int $userId = null): ?IpcClientEvent
    {
        try {
            $request ??= request();
            $kind = in_array($event['kind'] ?? null, IpcClientEvent::KINDS, true) ? $event['kind'] : 'js_error';
            $message = mb_substr(trim((string) ($event['message'] ?? '')), 0, 500);
            if ($message === '') {
                return null;
            }
            $url = isset($event['url']) ? mb_substr((string) $event['url'], 0, 300) : null;
            $ua = mb_substr((string) $request->userAgent(), 0, 300);
            $parsed = UserAgentSummary::parse($ua);

            $occurredAt = now();
            if (! empty($event['occurred_at'])) {
                try {
                    $given = Carbon::parse($event['occurred_at']);
                    // Offline-queued events carry their real time, but never a future/ancient one.
                    if ($given->lte(now()) && $given->gte(now()->subDays(7))) {
                        $occurredAt = $given;
                    }
                } catch (Throwable) {
                    // keep "now"
                }
            }

            return IpcClientEvent::create([
                'fingerprint' => self::fingerprint($kind, $message, $url),
                'kind' => $kind,
                'message' => $message,
                'detail' => isset($event['detail']) ? mb_substr((string) $event['detail'], 0, 4000) : null,
                'url' => $url,
                'user_id' => $userId ?? $request->user()?->id,
                'ipc_batch_id' => self::batchIdFromUrl($url),
                'user_agent' => $ua ?: null,
                'browser' => $parsed['browser'],
                'os' => $parsed['os'],
                'device' => $parsed['device'],
                'online' => isset($event['online']) ? (bool) $event['online'] : null,
                'net_type' => isset($event['net_type']) ? mb_substr((string) $event['net_type'], 0, 20) : null,
                'app_version' => isset($event['app_version']) ? mb_substr((string) $event['app_version'], 0, 40) : null,
                'occurred_at' => $occurredAt,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    public static function recordServerError(Throwable $e): void
    {
        $request = request();

        self::record([
            'kind' => 'server_error',
            'message' => class_basename($e).': '.$e->getMessage(),
            'detail' => $e->getFile().':'.$e->getLine(),
            'url' => '/'.ltrim($request->path(), '/'),
        ], $request);
    }

    /** Same problem = same kind + message (digits/hex ids stripped) + page (numeric ids stripped). */
    public static function fingerprint(string $kind, string $message, ?string $url): string
    {
        $msg = preg_replace('/\b[0-9a-f]{8,}\b|\d+/i', '#', mb_strtolower($message)) ?? $message;
        $path = preg_replace('/\d+/', '{n}', (string) parse_url((string) $url, PHP_URL_PATH)) ?? '';

        return sha1($kind.'|'.$msg.'|'.$path);
    }

    private static function batchIdFromUrl(?string $url): ?int
    {
        return $url !== null && preg_match('#/batches/(\d+)#', $url, $m) ? (int) $m[1] : null;
    }
}
