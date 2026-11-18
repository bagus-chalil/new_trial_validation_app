<?php

namespace App\Support;

/** Tiny User-Agent reader: just enough (browser, OS, device) to spot "all errors come from one phone/browser". */
class UserAgentSummary
{
    /** @return array{browser: string, os: string, device: string} */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;

        $browser = match (true) {
            (bool) preg_match('/MiuiBrowser\/([\d.]+)/', $ua, $m) => 'Mi Browser '.self::major($m[1]),
            (bool) preg_match('/SamsungBrowser\/([\d.]+)/', $ua, $m) => 'Samsung Internet '.self::major($m[1]),
            (bool) preg_match('/(?:Edg|EdgA|EdgiOS)\/([\d.]+)/', $ua, $m) => 'Edge '.self::major($m[1]),
            (bool) preg_match('/(?:OPR|Opera)\/([\d.]+)/', $ua, $m) => 'Opera '.self::major($m[1]),
            (bool) preg_match('/(?:Chrome|CriOS)\/([\d.]+)/', $ua, $m) => (str_contains($ua, '; wv)') ? 'WebView ' : 'Chrome ').self::major($m[1]),
            (bool) preg_match('/Firefox\/([\d.]+)/', $ua, $m) => 'Firefox '.self::major($m[1]),
            (bool) preg_match('/Version\/([\d.]+).*Safari/', $ua, $m) => 'Safari '.self::major($m[1]),
            default => 'Lainnya',
        };

        $os = match (true) {
            (bool) preg_match('/Android ([\d.]+)/', $ua, $m) => 'Android '.self::major($m[1]),
            (bool) preg_match('/(?:iPhone|iPad).*OS ([\d_]+)/', $ua, $m) => 'iOS '.self::major(str_replace('_', '.', $m[1])),
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Lainnya',
        };

        // Android UAs carry the model: "...; Redmi Note 11 Build/..." or "...; AGS5-W09)".
        $device = 'Lainnya';
        if (preg_match('/Android [\d.]+; ([^;)]+?)(?: Build\/[^;)]*)?[;)]/', $ua, $m)) {
            $device = trim($m[1]);
        } elseif (str_contains($ua, 'iPad')) {
            $device = 'iPad';
        } elseif (str_contains($ua, 'iPhone')) {
            $device = 'iPhone';
        } elseif (str_contains($ua, 'Windows') || str_contains($ua, 'Macintosh') || str_contains($ua, 'X11')) {
            $device = 'Komputer';
        }

        return ['browser' => $browser, 'os' => $os, 'device' => mb_substr($device, 0, 60)];
    }

    private static function major(string $version): string
    {
        return explode('.', $version)[0];
    }
}
