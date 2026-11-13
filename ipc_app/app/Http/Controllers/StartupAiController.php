<?php

namespace App\Http\Controllers;

use App\Actions\Vision\AnalyzeStartupPhoto;
use App\Http\Requests\StartupAiReadRequest;
use App\Models\IpcBatch;
use Illuminate\Http\JsonResponse;

/**
 * JSON endpoint behind the "Baca AI" button next to a Startup Check photo. Advisory only —
 * see AnalyzeStartupPhoto. Disabled (404) unless config('vision.startup_ai.enabled').
 */
class StartupAiController extends Controller
{
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
