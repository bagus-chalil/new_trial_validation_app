<?php

namespace App\Http\Controllers\Internal;

use App\Actions\Trials\SummarizeTrialStatusByFinishGoodCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TrialStatusController extends Controller
{
    public function index(SummarizeTrialStatusByFinishGoodCode $summarize): JsonResponse
    {
        return response()->json(['data' => $summarize()]);
    }
}
