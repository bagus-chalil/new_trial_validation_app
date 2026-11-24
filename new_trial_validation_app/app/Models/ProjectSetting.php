<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Key/value JSON settings of the Projects module (currently the PO template). */
class ProjectSetting extends Model
{
    protected $table = 'pm_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, mixed> */
    public static function read(string $key): array
    {
        $raw = static::query()->whereKey($key)->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /** @param  array<string, mixed>  $value */
    public static function write(string $key, array $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
