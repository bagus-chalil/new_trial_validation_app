<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IpcSetting extends Model
{
    public const LINE_MONITOR_TOKEN = 'line_monitor_public_token';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key): ?string
    {
        return static::query()->find($key)?->value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Token of the public Monitor Line board, created on first use. */
    public static function lineMonitorToken(): string
    {
        $token = static::get(self::LINE_MONITOR_TOKEN);
        if (blank($token)) {
            $token = static::regenerateLineMonitorToken();
        }

        return $token;
    }

    /** New token: the old public link (and any printed QR of it) stops working. */
    public static function regenerateLineMonitorToken(): string
    {
        $token = Str::random(40);
        static::put(self::LINE_MONITOR_TOKEN, $token);

        return $token;
    }
}
