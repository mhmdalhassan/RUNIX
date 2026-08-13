<?php

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Dispatch\DashboardController as DispatchDashboardController;
use App\Http\Controllers\Driver\AvailabilityController as DriverAvailabilityController;
use App\Http\Controllers\Driver\DashboardController as DriverDashboardController;
use App\Http\Controllers\Driver\LocationController as DriverLocationController;
use App\Http\Controllers\Driver\OrderController as DriverOrderController;
use App\Http\Controllers\Driver\OrderOfferController as DriverOrderOfferController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Phase 9 — public locale switch, no auth/role gate (spec §4). Placed
// alongside the other ungated routes below rather than inside any
// role-scoped group, since it must work for guests too.
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Phase 7 §1/§7 — public, unauthenticated order tracking. `{order:tracking_token}`
// overrides the binding key for this route only (Order::getRouteKeyName()
// stays untouched, so every {order} route elsewhere keeps binding by id).
// A token with no matching order throws ModelNotFoundException, which
// renders Laravel's normal 404 — no custom handling needed, and no way to
// distinguish "malformed token" from "token that just doesn't exist".
Route::get('/track/{order:tracking_token}', OrderTrackingController::class)->name('track.show');

// Sends every authenticated user to the dashboard for their own role.
Route::get('/dashboard', function (Request $request) {
    return match (true) {
        $request->user()->isSuperAdmin() => redirect()->route('admin.dashboard'),
        $request->user()->isDispatcher() => redirect()->route('dispatch.dashboard'),
        default => redirect()->route('driver.dashboard'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        // Staff Management: Dispatcher/Driver accounts only. Super Admin
        // accounts are out of scope for this UI — see UserController's
        // class docblock and User::scopeStaff().
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/password', [AdminUserController::class, 'updatePassword'])->name('users.password.update');
        Route::patch('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::patch('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');

        // System settings (e.g. the WhatsApp contact number) — a single
        // edit form over Setting's key/value store, Super Admin only.
        Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });

// Driver & Customer Management: Dispatcher and Super Admin both manage
// these day to day, so this group intentionally shares the /admin
// prefix with the Super-Admin-only group above but grants a wider role.
Route::middleware(['auth', 'verified', 'role:dispatcher,super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('drivers', AdminDriverController::class)->except('destroy');
        Route::patch('/drivers/{driver}/activate', [AdminDriverController::class, 'activate'])->name('drivers.activate');
        Route::patch('/drivers/{driver}/deactivate', [AdminDriverController::class, 'deactivate'])->name('drivers.deactivate');

        Route::resource('customers', AdminCustomerController::class);

        Route::resource('orders', AdminOrderController::class)->except('destroy');
        Route::patch('/orders/{order}/assign', [AdminOrderController::class, 'assign'])->name('orders.assign');
        Route::patch('/orders/{order}/transition', [AdminOrderController::class, 'transition'])->name('orders.transition');
    });

Route::middleware(['auth', 'verified', 'role:dispatcher,super_admin'])
    ->prefix('dispatch')
    ->name('dispatch.')
    ->group(function () {
        Route::get('/dashboard', DispatchDashboardController::class)->name('dashboard');
    });

Route::middleware(['auth', 'verified', 'role:driver'])
    ->prefix('driver')
    ->name('driver.')
    ->group(function () {
        Route::get('/dashboard', DriverDashboardController::class)->name('dashboard');

        Route::patch('/availability', [DriverAvailabilityController::class, 'toggle'])->name('availability.toggle');
        Route::patch('/location', [DriverLocationController::class, 'update'])->name('location.update');

        Route::get('/offers', [DriverOrderOfferController::class, 'index'])->name('offers.index');
        Route::patch('/offers/{offer}/accept', [DriverOrderOfferController::class, 'accept'])->name('offers.accept');
        Route::patch('/offers/{offer}/reject', [DriverOrderOfferController::class, 'reject'])->name('offers.reject');

        Route::get('/orders', [DriverOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [DriverOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/transition', [DriverOrderController::class, 'transition'])->name('orders.transition');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
