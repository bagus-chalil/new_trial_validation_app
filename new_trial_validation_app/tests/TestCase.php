<?php

namespace Tests;

use App\Services\Pdf\PdfRenderer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\FakePdfRenderer;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render Inertia pages through app.blade.php, whose
        // @vite() call needs public/build/manifest.json. CI's test stage runs
        // before (and without) `npm run build`, so stub Vite out — no test
        // asserts on built assets.
        $this->withoutVite();

        // Swap the real Browsershot-backed renderer for a fast fake — see
        // FakePdfRenderer's doc comment. Individual tests can still rebind
        // PdfRenderer::class if they need to assert something about the
        // real renderer.
        $this->app->bind(PdfRenderer::class, FakePdfRenderer::class);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
