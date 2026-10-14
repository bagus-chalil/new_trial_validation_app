<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One thing the self-learning job (`ai:learn`) derived from the data. Phase 1
 * is shadow mode: lessons are stored but nothing shows or acts on them yet.
 *
 * @property int $id
 * @property string $kind
 * @property string $scope_key
 * @property array<string, mixed> $payload
 * @property int $sample_size
 * @property string $confidence
 * @property string $status
 * @property int $version
 * @property Carbon|null $learned_at
 * @property Carbon|null $revoked_at
 */
#[Fillable(['kind', 'scope_key', 'payload', 'sample_size', 'confidence', 'status', 'version', 'learned_at', 'revoked_at'])]
class AiLesson extends Model
{
    public const KIND_WEIGHING_BASELINE = 'weighing_baseline';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $table = 'ai_lessons';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'learned_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
