<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * Skipped in local development: the default implementation hashes
     * public/build/manifest.json, a stale leftover from any earlier `npm run
     * build` (e.g. another session's CI-check pass, run while this app was
     * still being served by `npm run dev`/Vite HMR — HMR never touches that
     * file itself). If that file's hash changes while a page is already
     * open, the next Inertia request on that page gets treated as a version
     * mismatch: Inertia silently hard-reloads the current URL instead of
     * following the server's redirect, discarding whatever the user just
     * submitted and making a real "Save & Next" click look like it did
     * nothing. Asset versioning only matters for deployed builds, so this
     * class of dev-only spurious reload doesn't apply in local.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        if (app()->environment('local')) {
            return null;
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'canReviewTrials' => $request->user()?->isReviewer() ?? false,
            'canApproveTrials' => $request->user()?->canApproveTrials() ?? false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
