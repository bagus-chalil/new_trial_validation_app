<?php

namespace App\Services\Vision;

/**
 * Abstraction around the external Vision Service (Python OCR, separate server) so tests
 * can swap in Http::fake() or a fake implementation. See HttpVisionClient for the real one.
 * Implementations must never throw for "service down / bad response" — they return a
 * VisionResult with status ERROR so the caller routes it to REVIEW instead of crashing.
 */
interface VisionClient
{
    /**
     * @param  ?array{contents: string, filename: string}  $second  optional 2nd photo, sent as `file2` (coding_formula: tube other side)
     * @param  array<string, string>  $extra  extra form fields, e.g. ['coding_formula' => 'auto']
     */
    public function analyze(string $imageContents, string $filename, string $fieldType, string $requestId, ?array $second = null, array $extra = []): VisionResult;
}
