<?php

namespace App\Console\Commands;

use App\Models\AiBotInteraction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

#[Signature('ai:bot-prune {--days=90 : Delete bot questions older than this many days} {--dry-run : Only report how many rows would be deleted}')]
#[Description('Retention for ai_bot_interactions: deletes old questions, but keeps rows that carry a thumbs up/down (they feed the fix list and golden set).')]
class AiBotPrune extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('ai_bot_interactions')) {
            $this->error('ai_bot_interactions is missing; run migrations.');

            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $query = AiBotInteraction::query()
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->whereNull('feedback');

        if ($this->option('dry-run')) {
            $this->line("Would delete {$query->count()} bot question(s) older than {$days} days.");

            return self::SUCCESS;
        }

        $this->line("Deleted {$query->delete()} bot question(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
