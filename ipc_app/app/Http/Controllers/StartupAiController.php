<?php

namespace App\Http\Controllers;

use App\Actions\Vision\AnalyzeStartupPhoto;
use App\Actions\Vision\QueueStartupAiReads;
use App\Actions\Vision\StartupAiStatus;
use App\Http\Requests\StartupAiReadRequest;
use App\Http\Requests\StartupAiStatusRequest;
use App\Models\IpcBatch;
use Illuminate\Http\JsonResponse;

/**
 * JSON endpoint behind the "Baca AI" button next to a Startup Check photo. Advisory only —
 * see AnalyzeStartupPhoto. Disabled (404) unless config('vision.startup_ai.enabled').
 */
class StartupAiController extends Controller
{
    /**
     * Called by the page whenever a Startup photo or the packaging type changes: puts the missing
     * reads on the queue (idempotent) and returns the current status for the "Rekomendasi AI" card.
     */
    public function queue(StartupAiStatusRequest $request, IpcBatch $batch, QueueStartupAiReads $queue, StartupAiStatus $status): JsonResponse
    {
        abort_unless(config('vision.startup_ai.enabled'), 404);

        $entered = $request->entered();
        $queue->handle($batch, $request->user(), $entered['packaging_type']);

        return response()->json($status->handle($batch, $entered));
    }

    /** Polled by the card while reads are pending, and re-called when the typed EXP/mixing date changes. */
    public function status(StartupAiStatusRequest $request, IpcBatch $batch, StartupAiStatus $status): JsonResponse
    {
        abort_unless(config('vision.startup_ai.enabled'), 404);

        return response()->json($status->handle($batch, $request->entered()));
    }

    public function read(StartupAiReadRequest $request, IpcBatch $batch, AnalyzeStartupPhoto $action): JsonResponse
    {
        abort_unless(config('vision.startup_ai.enabled'), 404);

        // Main LLM read + (tube) PaddleOCR read + tube_exp_llm read (25-80s on OK), queued behind other stations.
        set_time_limit((int) config('vision.timeout') + (int) config('vision.llm_timeout') * 2 + 30);

        $out = $action->handle($batch, $request->user(), $request->validated('field'), [
            'mixing_date' => $request->validated('mixing_date'),
            'exp_date' => $request->validated('exp_date'),
            'shelf_life_months' => $request->validated('shelf_life_months'),
            'packaging_type' => $request->validated('packaging_type'),
        ]);

        if ($out === null) {
            return response()->json(['message' => 'Foto belum diambil / disimpan untuk field ini.'], 422);
        }

        return response()->json([
            'log_id' => $out['log']->id,
            'field' => $request->validated('field'),
            'decision' => $out['evaluation']->decision,
            'reason' => $out['evaluation']->reason,
            'items' => $out['evaluation']->items,
            'vision_status' => $out['log']->vision_status,
            'confidence' => $out['log']->confidence,
        ]);
    }
}
