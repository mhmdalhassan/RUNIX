<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffPasswordRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->staff()
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('role')->value(), function ($query, string $role) {
                $query->where('role', $role);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $request->string('search')->value(),
            'role' => $request->string('role')->value(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create');
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::from($validated['role']),
            ]);

            if ($user->role === UserRole::DRIVER) {
                Driver::create([
                    'user_id' => $user->id,
                    'phone' => $validated['phone'],
                    'is_active' => true,
                    'is_online' => false,
                ]);
            }
        });

        return redirect()->route('admin.users.index')
            ->with('status', 'Staff account created.');
    }

    public function edit(User $user): View
    {
        abort_if($user->isSuperAdmin(), 404);

        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateStaffRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 404);

        $user->update($request->validated());

        return redirect()->route('admin.users.index')
            ->with('status', 'Staff account updated.');
    }

    public function updatePassword(UpdateStaffPasswordRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 404);

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return back()->with('status', 'Password updated.');
    }

    public function activate(User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 404);

        Gate::authorize('update', $user);

        $user->update(['is_active' => true]);

        return back()->with('status', 'Account activated.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 404);

        Gate::authorize('update', $user);

        $user->update(['is_active' => false]);

        return back()->with('status', 'Account deactivated.');
    }
}
