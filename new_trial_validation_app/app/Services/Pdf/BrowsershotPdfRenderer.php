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
 *
 * Puppeteer resolves its cache directory from the *current process's*
 * $HOME by default. On the deploy servers, CI installs the browser as the
 * gitlab-runner user, but PHP-FPM serves requests as www-data (HOME
 * /var/www) — two different HOMEs, so the browser CI downloaded was
 * invisible at request time ("Could not find chrome-headless-shell").
 * Pinning PUPPETEER_CACHE_DIR to a path inside this app fixes that for any
 * user; .gitlab-ci.yml's puppeteer install step must use the same path.
 */
class BrowsershotPdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        putenv('PUPPETEER_CACHE_DIR='.storage_path('app/puppeteer-cache'));

        return Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->timeout(60)
            ->pdf();
    }
}
