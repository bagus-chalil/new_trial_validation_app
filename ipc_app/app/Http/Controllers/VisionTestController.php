<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestVisionRequest;
use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Services\Vision\VisionClient;
use App\Services\Vision\VisionEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "OCR Test Product" sandbox: try a photo against the Vision Service without a real batch.
 * Nothing is persisted — no photo is stored and no ipc_logs row is written, so production
 * scan history stays production-only. The decision still comes from the same, unmodified
 * VisionEvaluator, fed an unsaved in-memory IpcBatch carrying the EXP date typed on the page
 * and the chosen product (for its shelf-life), so the sandbox can never be more lenient
 * than a real stage scan.
 */
class VisionTestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('vision-test/index', [
            'fieldTypes' => config('vision.field_types'),
            'rules' => config('vision.rules'),
        ]);
    }

    public function analyze(TestVisionRequest $request, VisionClient $client, VisionEvaluator $evaluator): JsonResponse
    {
        // Same reason as VisionController::analyze(): one OCR call is ~25s and queues.
        set_time_limit(config('vision.timeout') + 30);

        $photo = $request->file('photo');
        $fieldType = $request->validated('field_type');
        $requestId = (string) Str::uuid();

        $batch = new IpcBatch;
        $batch->exp_date = $request->validated('exp_date');
        $batch->setRelation('masterProduct', MasterProduct::find($request->validated('master_product_id')));

        $result = $client->analyze($photo->get(), $photo->getClientOriginalName() ?: 'capture.jpg', $fieldType, $requestId);
        $evaluation = $evaluator->evaluate($batch, $fieldType, $result);

        return response()->json([
            'request_id' => $requestId,
            'field_type' => $fieldType,
            'decision' => $evaluation->decision,
            'decision_reason' => $evaluation->reason,
            'ocr_value' => $result->value,
            'raw_ocr_text' => $result->rawText,
            'expected_value' => $evaluation->expectedValue,
            'computed_value' => $evaluation->computedValue,
            'vision_status' => $result->status,
            'confidence' => $result->confidence,
            'format_valid' => $result->formatValid,
            'error_reason' => $result->errorReason,
            'engine_used' => $result->engineUsed,
            'processing_time_ms' => $result->processingTimeMs,
        ]);
    }
}
