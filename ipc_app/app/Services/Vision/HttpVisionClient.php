<?php

namespace App\Services\Vision;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Real VisionClient: POST {vision.base_url}/api/analyze as multipart (file [+ file2] + field_type +
 * request_id [+ extra fields such as coding_formula]). debug/reference_date are deliberately NOT sent — debug adds large base64
 * crops to the response, and the reference-date comparison is done by VisionEvaluator in
 * Laravel, not by the Vision Service.
 */
class HttpVisionClient implements VisionClient
{
    public function analyze(string $imageContents, string $filename, string $fieldType, string $requestId, ?array $second = null, array $extra = []): VisionResult
    {
        $timeout = in_array($fieldType, config('vision.llm_field_types', []), true)
            ? max((int) config('vision.timeout'), (int) config('vision.llm_timeout'))
            : (int) config('vision.timeout');

        try {
            $request = Http::baseUrl(rtrim(config('vision.base_url'), '/'))
                ->timeout($timeout)
                ->connectTimeout(config('vision.connect_timeout'))
                ->acceptJson()
                ->attach('file', $imageContents, $filename);

            if ($second !== null) {
                $request = $request->attach('file2', $second['contents'], $second['filename']);
            }

            $response = $request->post('/api/analyze', [
                ...$extra,
                'field_type' => $fieldType,
                'request_id' => $requestId,
            ]);
        } catch (ConnectionException $e) {
            return VisionResult::error('VISION_UNREACHABLE: '.$e->getMessage());
        }

        if (! $response->successful()) {
            // e.g. coding_formula: HTTP 400 {"status":"ERROR","error":"FORMULA_INVALID: ..."}.
            $detail = $response->json('error');

            return VisionResult::error('VISION_HTTP_'.$response->status().(is_string($detail) ? ': '.mb_substr($detail, 0, 200) : ''));
        }

        $data = $response->json();

        if (! is_array($data)) {
            return VisionResult::error('VISION_INVALID_JSON');
        }

        // main.py returns {"error": "..."} (no status key) when the image can't be decoded.
        if (isset($data['error']) && ! isset($data['status'])) {
            return VisionResult::error('VISION_ERROR: '.$data['error']);
        }

        return VisionResult::fromResponse($data);
    }
}
