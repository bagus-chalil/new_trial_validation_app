<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One question asked to the trial bot (see the migration for why it is kept).
 *
 * @property int $id
 * @property string|null $user_ref
 * @property string $locale
 * @property string $question
 * @property string|null $intent
 * @property bool $matched
 * @property string|null $answer_summary
 * @property int|null $feedback
 * @property Carbon|null $feedback_at
 * @property Carbon|null $created_at
 */
#[Fillable(['user_ref', 'locale', 'question', 'intent', 'matched', 'answer_summary', 'feedback', 'feedback_at', 'from_id'])]
class AiBotInteraction extends Model
{
    protected $table = 'ai_bot_interactions';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'feedback_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
