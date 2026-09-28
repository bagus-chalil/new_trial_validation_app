<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Pdf\BrowsershotPdfRenderer;
use App\Services\Pdf\PdfRenderer;
use App\Services\Vision\HttpVisionClient;
use App\Services\Vision\VisionClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, BrowsershotPdfRenderer::class);
        $this->app->bind(VisionClient::class, HttpVisionClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-master', fn (User $user) => $user->isAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());
        Gate::define('approve-ipc', fn (User $user) => $user->isApprover());
        Gate::define('use-vision-test', fn (User $user) => $user->isStaff());
    }
}
