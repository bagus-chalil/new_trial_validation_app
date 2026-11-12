<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_app_version_endpoint_returns_uncached_inertia_version(): void
    {
        $response = $this->getJson('/app-version');

        $response->assertOk()->assertJsonStructure(['version']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
