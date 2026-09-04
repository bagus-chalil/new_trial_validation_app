<?php

namespace Tests;

use App\Services\Pdf\PdfRenderer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakePdfRenderer;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(PdfRenderer::class, FakePdfRenderer::class);
    }
}
