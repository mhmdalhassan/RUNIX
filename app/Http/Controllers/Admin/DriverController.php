<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDriverRequest;
use App\Http\Requests\Admin\UpdateDriverRequest;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class DriverController extends Controller
{
    /**
     * List drivers, with optional search/status filtering.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Driver::class);

        $drivers = Driver::query()
            ->with('user')
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($query) use ($search) {
                    $query->where('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->string('status')->value(), function ($query, string $status) {
                match ($status) {
                    'active' => $query->where('is_active', true),
                    'inactive' => $query->where('is_active', false),
                    'online' => $query->where('is_online', true),
                    'offline' => $query->where('is_online', false),
                    default => null,
                };
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.drivers.index', [
            'drivers' => $drivers,
            'search' => $request->string('search')->value(),
            'status' => $request->string('status')->value(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Driver::class);

        return view('admin.drivers.create');
    }

    /**
     * Creates the driver's User + Driver rows atomically: if either
     * write fails, both roll back together.
     */
    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $driver = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::DRIVER,
            ]);

            return Driver::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
                'is_active' => $validated['is_active'] ?? true,
                'is_online' => $validated['is_online'] ?? false,
            ]);
        });

        return redirect()->route('admin.drivers.show', $driver)
            ->with('status', 'Driver created.');
    }

    public function show(Driver $driver): View
    {
        Gate::authorize('view', $driver);

        return view('admin.drivers.show', [
            'driver' => $driver->load('user'),
            'deliveryHistory' => $driver->deliveryHistoryQuery()->paginate(15),
            'averageRating' => $driver->averageRating(),
            'feedbackCount' => $driver->feedback()->count(),
            'recentFeedback' => $driver->feedback()->limit(10)->get(),
        ]);
    }

    public function edit(Driver $driver): View
    {
        Gate::authorize('update', $driver);

        return view('admin.drivers.edit', [
            'driver' => $driver->load('user'),
        ]);
    }

    /**
     * Updates the linked User + Driver rows atomically.
     */
    public function update(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $driver) {
            $driver->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            $driver->update([
                'phone' => $validated['phone'],
                'is_active' => $validated['is_active'] ?? false,
                'is_online' => $validated['is_online'] ?? false,
            ]);
        });

        return redirect()->route('admin.drivers.show', $driver)
            ->with('status', 'Driver updated.');
    }

    public function activate(Driver $driver): RedirectResponse
    {
        Gate::authorize('update', $driver);

        $driver->update(['is_active' => true]);

        return back()->with('status', 'Driver activated.');
    }

    public function deactivate(Driver $driver): RedirectResponse
    {
        Gate::authorize('update', $driver);

        $driver->update(['is_active' => false]);

        return back()->with('status', 'Driver deactivated.');
    }
}
