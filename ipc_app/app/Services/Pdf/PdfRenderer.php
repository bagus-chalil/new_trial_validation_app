<?php

namespace App\Services\Pdf;

/**
 * Abstraction around "turn this HTML string into PDF bytes" so tests can
 * swap in a fake instead of shelling out to a real headless Chrome via
 * Browsershot on every request. See BrowsershotPdfRenderer for the real
 * implementation.
 */
interface PdfRenderer
{
    public function render(string $html): string;
}
