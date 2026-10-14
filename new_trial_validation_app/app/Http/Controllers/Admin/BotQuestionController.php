<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiBotInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only admin view of what people asked the trial bot. This is the
 * maintenance loop for a rule-based bot: unanswered questions are the backlog
 * of intents/aliases to add, thumbs-down answers are the ones to fix.
 * Question text is not scrubbed, so this stays admin-only (`manage-settings`).
 */
class BotQuestionController extends Controller
{
    private const VIEWS = ['unanswered', 'downvoted', 'all'];

    public function index(Request $request): Response
    {
        Gate::authorize('manage-settings');

        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'unanswered';

        $rows = match ($view) {
            // Same question asked repeatedly is one backlog item, most asked first.
            'unanswered' => AiBotInteraction::query()
                ->where('matched', false)
                ->select([
                    DB::raw('MIN(id) as id'),
                    DB::raw('LOWER(question) as question_key'),
                    DB::raw('MIN(question) as question'),
                    DB::raw('COUNT(*) as asked_count'),
                    DB::raw('MAX(created_at) as last_asked_at'),
                    DB::raw('MIN(locale) as locale'),
                ])
                ->groupBy(DB::raw('LOWER(question)'))
                ->orderByDesc('asked_count')
                ->orderByDesc('last_asked_at')
                ->paginate(20)
                ->withQueryString(),
            'downvoted' => AiBotInteraction::query()
                ->where('feedback', -1)
                ->orderByDesc('feedback_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            default => AiBotInteraction::query()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
        };

        $total = AiBotInteraction::query()->count();
        $unmatched = AiBotInteraction::query()->where('matched', false)->count();

        return Inertia::render('admin/bot-questions/index', [
            'view' => $view,
            'rows' => $rows,
            'stats' => [
                'total' => $total,
                'unmatched' => $unmatched,
                'upvoted' => AiBotInteraction::query()->where('feedback', 1)->count(),
                'downvoted' => AiBotInteraction::query()->where('feedback', -1)->count(),
            ],
        ]);
    }
}
