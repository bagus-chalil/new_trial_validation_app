<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MasterLine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category',
        'area',
        'code',
        'name',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (MasterLine $line) {
            $line->public_token ??= Str::random(32);
        });
    }

    /** Public URL the line's QR code points at (read-only status page, no login). */
    public function publicUrl(): string
    {
        return route('line-status.show', $this->public_token);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
