<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
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
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $request->only('q'),
            'roles' => User::ROLES,
            'roleLabels' => User::ROLE_LABELS,
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
        $user->role = $request->validated('role');
        $user->save();

        return back()->with('success', "Role {$user->name} diperbarui.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', $user->is_active ? "User {$user->name} diaktifkan." : "User {$user->name} dinonaktifkan.");
    }
}
