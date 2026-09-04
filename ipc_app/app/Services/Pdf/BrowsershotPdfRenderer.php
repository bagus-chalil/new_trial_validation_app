<?php

namespace App\Services\Pdf;

use Spatie\Browsershot\Browsershot;

/**
 * Real PDF rendering via spatie/browsershot (headless Chrome/Puppeteer),
 * same choice and same reasoning as ../../../../new_trial_validation_app's
 * own copy of this class: the report layouts (info grids, checklist tables,
 * photo galleries) lean on CSS grid/flexbox that dompdf's engine renders
 * poorly, while Browsershot renders through real Chrome.
 *
 * Requires the Chromium build Puppeteer manages to already be downloaded on
 * this machine (`node node_modules/puppeteer/install.mjs`, or `npx puppeteer
 * browsers install chrome chrome-headless-shell`) — a plain `npm install`
 * alone does not guarantee this if install scripts are gated in this
 * environment.
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
