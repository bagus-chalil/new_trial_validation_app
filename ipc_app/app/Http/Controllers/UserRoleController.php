<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roleRecord')
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('id')->get(['code', 'label']);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $request->only('q'),
            'roles' => $roles->pluck('code'),
            'roleLabels' => $roles->pluck('label', 'code'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => $request->validated('role'),
        ]);

        return back()->with('success', "User {$user->name} ditambahkan.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($error = $this->roleChangeError($request->user(), $user, $request->validated('role'))) {
            return back()->with('error', $error);
        }

        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        $user->role = $request->validated('role');

        if ($request->validated('password')) {
            $user->password = Hash::make($request->validated('password'));
        }

        $user->save();

        return back()->with('success', "User {$user->name} diperbarui.");
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        if ($error = $this->roleChangeError($request->user(), $user, $request->validated('role'))) {
            return back()->with('error', $error);
        }

        $user->role = $request->validated('role');
        $user->save();

        return back()->with('success', "Role {$user->name} diperbarui.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        if ($user->is_active && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Tidak bisa menonaktifkan Admin aktif terakhir.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', $user->is_active ? "User {$user->name} diaktifkan." : "User {$user->name} dinonaktifkan.");
    }

    /**
     * USR-05 / SIT H5: an Admin must not be able to lock themselves out by changing their own
     * role, and the app must never be left without an active Admin (recovery would need DB access).
     */
    private function roleChangeError(User $actor, User $target, string $newRole): ?string
    {
        if ($newRole === $target->role) {
            return null;
        }

        if ($actor->id === $target->id) {
            return 'Tidak bisa mengubah role akun sendiri.';
        }

        if ($this->isLastActiveAdmin($target)) {
            return 'Tidak bisa menurunkan role Admin aktif terakhir.';
        }

        return null;
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if ($user->role !== User::ROLE_ADMIN || ! $user->is_active) {
            return false;
        }

        return User::query()
            ->where('role_id', $user->role_id)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }
}
