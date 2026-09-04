<?php

namespace Tests\Support;

use App\Services\Pdf\PdfRenderer;

/**
 * Swapped in for every test (see TestCase::setUp()) so PDF export tests
 * don't shell out to a real headless Chrome via Browsershot — fast and
 * deterministic, and doesn't require Chromium to be installed in CI. Mirrors
 * ../../../new_trial_validation_app/tests/Support/FakePdfRenderer.php.
 */
class FakePdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        return "%PDF-FAKE\n".$html;
    }
}
