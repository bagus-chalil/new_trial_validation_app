<?php

namespace App\Http\Controllers;

use App\Models\Trial;
use App\Services\Ai\TrialSummaryAnalyzer;
use App\Services\Ai\TrialSummaryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * "Tulis ringkasan AI" on the per-trial Report Summary. Access is the existing TrialPolicy::view plus
 * the audience of the card (approvers and department reviewers); no new access rule.
 */
class TrialSummaryController extends Controller
{
    public function store(Request $request, int $trial, TrialSummaryAnalyzer $analyzer): JsonResponse
    {
        abort_unless(config('ai.insights.enabled'), 404);

        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        Gate::authorize('view', $trial);
        abort_unless(TrialSummaryBuilder::audience($request->user()), 403);

        // The Ollama server is serial, so a long summary must not be stacked by double clicks.
        $lock = Cache::lock('trial-summary-ai', 200);
        if (! $lock->get()) {
            return response()->json(['ok' => false, 'error' => __('insight.errors.busy')], 429);
        }

        try {
            return response()->json($analyzer->analyze($trial, app()->getLocale()));
        } finally {
            $lock->release();
        }
    }
}
