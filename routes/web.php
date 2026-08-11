<?php

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Dispatch\DashboardController as DispatchDashboardController;
use App\Http\Controllers\Driver\DashboardController as DriverDashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
