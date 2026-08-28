<?php

namespace App\Services\Pdf;

use Spatie\Browsershot\Browsershot;

/**
 * Real PDF rendering via spatie/browsershot (headless Chrome/Puppeteer).
 * Chosen over barryvdh/laravel-dompdf — see ../../../CLAUDE.md's "Print/PDF
 * report approach" entry — because the report layouts (info grids,
 * photo-attachment galleries) lean on CSS grid/flexbox that dompdf's engine
 * renders poorly, while Browsershot renders through real Chrome.
 *
 * Requires the Chromium build(s) Puppeteer manages to already be downloaded
 * on this machine (`npx puppeteer browsers install chrome
 * chrome-headless-shell`) — this project's .npmrc sets ignore-scripts=true,
 * so a plain `npm install` does NOT fetch them automatically the way a
 * default Puppeteer install would. `composer run setup` runs this
 * explicitly; a machine that skipped `setup` needs it run by hand once.
 */
class BrowsershotPdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        return Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->timeout(60)
            ->pdf();
    }
}
