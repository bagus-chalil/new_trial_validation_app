<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "Login sebagai" for debugging/support: an Admin switches into another user's account without
 * knowing their password, then switches back. Same design as new_trial_validation_app's
 * Admin\ImpersonationController — chosen over a shared master password because every switch is
 * attributable (start and end are logged under the admin's own id) and the impersonated session
 * carries a banner on every page (`impersonator` shared prop, see HandleInertiaRequests).
 *
 * Starting is limited to Admin by the route's `can:manage-users` middleware; leaving is reached
 * while logged in as the impersonated (usually non-admin) user, so it is authorised by the
 * impersonator id stored in the session, not by a Gate.
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($request->session()->has(self::SESSION_KEY), 403, 'Sedang login sebagai user lain. Kembali ke akun Admin dulu.');

        $admin = $request->user();

        abort_if($user->id === $admin->id, 403, 'Tidak bisa login sebagai akun sendiri.');
        abort_unless($user->is_active, 403, 'User nonaktif tidak bisa dipakai login.');

        Log::notice('IPC impersonation started', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'target_id' => $user->id,
            'target_email' => $user->email,
            'ip' => $request->ip(),
        ]);

        Auth::login($user);
        $request->session()->put(self::SESSION_KEY, $admin->id);

        return to_route('dashboard')->with('success', "Anda sekarang login sebagai {$user->name}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);
        $admin = is_int($adminId) ? User::query()->where('is_active', true)->find($adminId) : null;

        abort_if($admin === null || ! $admin->isAdmin(), 403);

        $impersonated = $request->user();

        Auth::login($admin);

        Log::notice('IPC impersonation ended', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'target_id' => $impersonated?->id,
            'target_email' => $impersonated?->email,
            'ip' => $request->ip(),
        ]);

        return to_route('users.index')->with('success', 'Kembali ke akun Admin.');
    }
}
