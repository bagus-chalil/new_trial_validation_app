<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bot\StoreBotAliasRequest;
use App\Models\AiBotAlias;
use App\Models\AiBotInteraction;
use App\Models\MasterOption;
use App\Services\Ai\Bot\AliasSuggester;
use App\Services\Ai\Bot\IntentRecognizer;
use App\Services\Ai\Bot\QuestionClusterer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $phrases = collect([$data['phrase'], ...($data['also'] ?? [])])
            ->map(fn ($phrase) => AiBotAlias::normalize((string) $phrase))
            ->filter(fn (string $phrase) => mb_strlen($phrase) >= 3)
            ->unique()
            ->values();

        foreach ($phrases as $phrase) {
            AiBotAlias::query()->updateOrCreate(
                ['phrase' => $phrase],
                [
                    'target' => $data['target'],
                    'status' => $data['target'] === 'trials_by_status' ? ($data['status'] ?? null) : null,
                    'created_by' => $request->user()?->id,
                ],
            );
        }

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
     * Unmatched questions grouped by wording, similar wordings merged into one
     * cluster (most asked first), minus the ones the bot can understand by now
     * (taught or built in) and the ignored ones. Each cluster carries the bot's
     * suggested meaning: what users clicked on a "did you mean" beats text similarity.
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

        $groups = [];
        foreach (AiBotInteraction::query()->where('matched', false)->orderBy('id')->get(['id', 'question', 'locale', 'created_at']) as $row) {
            $key = mb_strtolower((string) $row->question);
            $groups[$key] ??= ['question' => (string) $row->question, 'asked_count' => 0, 'ids' => [], 'last_asked_at' => '', 'locale' => (string) $row->locale];
            $groups[$key]['asked_count']++;
            $groups[$key]['ids'][] = $row->id;
            $groups[$key]['last_asked_at'] = max($groups[$key]['last_asked_at'], (string) $row->created_at);
        }

        $groups = collect($groups)
            ->reject(fn (array $g) => isset($ignored[AiBotAlias::normalize($g['question'])])
                || $recognizer->recognize($g['question'], $productTypes, $aliases)['intent'] !== IntentRecognizer::INTENT_UNKNOWN)
            ->values()
            ->all();

        $clusters = collect((new QuestionClusterer)->cluster($groups));

        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));
        $pageItems = $clusters->forPage($page, $perPage)->values();

        $suggester = new AliasSuggester($recognizer);
        $items = $pageItems->map(function (array $cluster) use ($suggester, $aliases) {
            $cluster['suggestion'] = $this->clickedSuggestion($cluster['ids'])
                ?? $this->textSuggestion($suggester, $cluster['question'], $aliases);

            return $cluster;
        });

        return new LengthAwarePaginator(
            $items,
            $clusters->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * The intent users most often landed on after clicking a suggestion made for one of these questions.
     *
     * @param  array<int, int>  $ids
     * @return array{target:string, clicked:bool, score:float}|null
     */
    private function clickedSuggestion(array $ids): ?array
    {
        $intents = AiBotInteraction::query()
            ->whereIn('from_id', $ids)
            ->whereNotNull('intent')
            ->pluck('intent')
            ->filter(fn ($intent) => in_array($intent, AiBotAlias::TARGETS, true));

        if ($intents->isEmpty()) {
            return null;
        }

        $top = $intents->countBy()->sortDesc()->keys()->first();

        return ['target' => (string) $top, 'clicked' => true, 'score' => 1.0];
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return array{target:string, clicked:bool, score:float}|null
     */
    private function textSuggestion(AliasSuggester $suggester, string $question, array $aliases): ?array
    {
        $best = $suggester->suggest($question, $aliases, 1)[0] ?? null;

        return $best === null ? null : ['target' => $best['intent'], 'clicked' => false, 'score' => round($best['score'], 2)];
    }
}
