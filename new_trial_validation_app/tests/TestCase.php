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
