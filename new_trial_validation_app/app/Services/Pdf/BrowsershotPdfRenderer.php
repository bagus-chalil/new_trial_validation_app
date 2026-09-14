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
 * on this machine via two separate calls — `npx puppeteer browsers install
 * chrome` then `npx puppeteer browsers install chrome-headless-shell` — NOT
 * one call with both names as positional args (`... install chrome
 * chrome-headless-shell`), which silently installs only the first and was
 * the actual cause of "Could not find chrome-headless-shell" recurring in
 * both production and development on 2026-09-14 despite CI/`setup`
 * "succeeding" on every prior deploy. This project's .npmrc sets
 * ignore-scripts=true, so a plain `npm install` does NOT fetch them
 * automatically the way a default Puppeteer install would. `composer run
 * setup` runs this explicitly; a machine that skipped `setup` needs it run
 * by hand once.
 *
 * Puppeteer resolves its cache directory from the *current process's*
 * $HOME by default. On the deploy servers, CI installs the browser as the
 * gitlab-runner user, but PHP-FPM serves requests as www-data (HOME
 * /var/www) — two different HOMEs, so the browser CI downloaded was
 * invisible at request time ("Could not find chrome-headless-shell").
 * Pinning PUPPETEER_CACHE_DIR to a path inside this app fixes that for any
 * user; .gitlab-ci.yml's puppeteer install step must use the same path.
 *
 * Must be set via $_ENV, not just putenv(): under PHP-FPM (unlike the CLI
 * SAPI), Symfony Process's default child-process environment is getenv()
 * intersected with $_SERVER's keys (see Process::getDefaultEnv()) — a
 * putenv()-only var isn't in $_SERVER, so it gets silently dropped before
 * reaching the spawned `node` process. $_ENV is merged in unconditionally,
 * bypassing that filter.
 *
 * ->noSandbox(): Chrome's sandbox needs unprivileged user namespaces, which
 * Ubuntu 23.10+ restricts by default via AppArmor — without this, launch
 * fails outright with "FATAL:zygote_host_impl_linux.cc No usable sandbox!"
 * (hit on the real Ubuntu 26.04 production/development servers, 2026-09-14).
 * Accepted here because the HTML rendered is always our own server-generated
 * Blade output (trials.report / *.blade.php under resources/views/pdf), not
 * an arbitrary user-supplied URL or third-party page — the sandbox's real
 * threat model (a malicious remote page escaping the renderer) doesn't
 * apply. The AppArmor-profile alternative (letting the sandbox work as
 * intended) needs a root-level, Ubuntu-version-specific policy on every
 * deploy server and was not pursued.
 */
class BrowsershotPdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        $cacheDir = storage_path('app/puppeteer-cache');
        putenv("PUPPETEER_CACHE_DIR={$cacheDir}");
        $_ENV['PUPPETEER_CACHE_DIR'] = $cacheDir;

        return Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->timeout(60)
            ->noSandbox()
            ->pdf();
    }
}
