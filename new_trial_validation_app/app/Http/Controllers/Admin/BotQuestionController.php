<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bot\StoreBotAliasRequest;
use App\Models\AiBotAlias;
use App\Models\AiBotInteraction;
use App\Models\MasterOption;
use App\Services\Ai\Bot\IntentRecognizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only admin view of what people asked the trial bot. This is the
 * maintenance loop for a rule-based bot: unanswered questions are the backlog
 * of intents/aliases to add, thumbs-down answers are the ones to fix.
 * Question text is not scrubbed, so this stays admin-only (`manage-settings`).
 *
 * Admins also "answer" an unanswered question here without any code: they map
 * its wording to one of the bot's fixed intents (AiBotAlias) or ignore it.
 */
class BotQuestionController extends Controller
{
    private const VIEWS = ['unanswered', 'downvoted', 'all', 'rules'];

    public function index(Request $request): Response
    {
        Gate::authorize('manage-settings');

        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'unanswered';

        $rows = match ($view) {
            'unanswered' => $this->unanswered($request),
            'rules' => AiBotAlias::query()->orderByDesc('id')->paginate(20)->withQueryString(),
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
            'targets' => AiBotAlias::TARGETS,
            'statuses' => AiBotAlias::STATUSES,
            'stats' => [
                'total' => $total,
                'unmatched' => $unmatched,
                'upvoted' => AiBotInteraction::query()->where('feedback', 1)->count(),
                'downvoted' => AiBotInteraction::query()->where('feedback', -1)->count(),
            ],
        ]);
    }

    public function storeAlias(StoreBotAliasRequest $request): RedirectResponse
    {
        $data = $request->validated();

        AiBotAlias::query()->updateOrCreate(
            ['phrase' => AiBotAlias::normalize($data['phrase'])],
            [
                'target' => $data['target'],
                'status' => $data['target'] === 'trials_by_status' ? ($data['status'] ?? null) : null,
                'created_by' => $request->user()?->id,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.toast.bot_alias_saved')]);

        return back();
    }

    public function destroyAlias(AiBotAlias $alias): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $alias->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.toast.bot_alias_deleted')]);

        return back();
    }

    /**
     * Unmatched questions grouped by wording, most asked first, minus the ones
     * the bot can understand by now (taught or built in) and the ignored ones.
     *
     * @return LengthAwarePaginator<int, mixed>
     */
    private function unanswered(Request $request): LengthAwarePaginator
    {
        $aliases = AiBotAlias::grouped();
        $ignored = array_flip($aliases['ignore']);
        $recognizer = new IntentRecognizer;
        $productTypes = MasterOption::query()
            ->where('type', 'product_type')->where('is_active', true)->whereNull('deleted_at')
            ->pluck('name')->all();

        $groups = AiBotInteraction::query()
            ->where('matched', false)
            ->select([
                DB::raw('MIN(id) as id'),
                DB::raw('MIN(question) as question'),
                DB::raw('COUNT(*) as asked_count'),
                DB::raw('MAX(created_at) as last_asked_at'),
                DB::raw('MIN(locale) as locale'),
            ])
            ->groupBy(DB::raw('LOWER(question)'))
            ->orderByDesc('asked_count')
            ->orderByDesc('last_asked_at')
            ->get()
            ->reject(fn ($row) => isset($ignored[AiBotAlias::normalize((string) $row->question)])
                || $recognizer->recognize((string) $row->question, $productTypes, $aliases)['intent'] !== IntentRecognizer::INTENT_UNKNOWN)
            ->values();

        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
