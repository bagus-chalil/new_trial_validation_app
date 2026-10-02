<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * "Login as User" for debugging: an Admin/Super Admin switches into another
 * user's account without knowing their password, then switches back. Chosen
 * over a shared master password so every switch is attributable — both the
 * start and the end are written to activity_logs under the admin's own name,
 * and the impersonated session carries a visible banner (see the
 * `impersonator` shared prop in HandleInertiaRequests).
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($request->session()->has(self::SESSION_KEY), 403, __('admin.impersonation.already_active'));

        Gate::authorize('impersonate', $user);

        $admin = $request->user();

        $this->log($request, $admin, 'IMPERSONATE_START', $user);

        Auth::login($user);
        $request->session()->put(self::SESSION_KEY, $admin->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.toast.impersonation_started', ['name' => $user->name])]);

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);
        $admin = is_int($adminId) ? User::whereNull('deleted_at')->where('is_active', 1)->find($adminId) : null;

        abort_if($admin === null, 403);

        $impersonated = $request->user();

        Auth::login($admin);

        $this->log($request, $admin, 'IMPERSONATE_END', $impersonated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.toast.impersonation_ended')]);

        return to_route('admin.users.index');
    }

    private function log(Request $request, User $admin, string $action, User $target): void
    {
        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => $action,
            'module' => 'AUTH',
            'record_id' => (string) $target->id,
            'record_label' => $target->email,
            'old_data' => null,
            'new_data' => json_encode(['target_user_id' => $target->id, 'target_email' => $target->email]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
