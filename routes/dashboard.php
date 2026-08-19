<?php

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Admin\ExpenseController as AdminExpenseController;
use App\Http\Controllers\Admin\MenuCategoryController as AdminMenuCategoryController;
use App\Http\Controllers\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\RestaurantController as AdminRestaurantController;
use App\Http\Controllers\Admin\RestaurantStatusPreviewController as AdminRestaurantStatusPreviewController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Dispatch\DashboardController as DispatchDashboardController;
use App\Http\Controllers\Driver\AvailabilityController as DriverAvailabilityController;
use App\Http\Controllers\Driver\AvailableOrdersController as DriverAvailableOrdersController;
use App\Http\Controllers\Driver\DashboardController as DriverDashboardController;
use App\Http\Controllers\Driver\LocationController as DriverLocationController;
use App\Http\Controllers\Driver\OrderController as DriverOrderController;
use App\Http\Controllers\Driver\OrderOfferController as DriverOrderOfferController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Everything behind a login: the staff/dispatch/driver dashboard.
// routes/web.php (which requires this file, not the other way around)
// holds the public website instead.

// Sends every authenticated user to the dashboard for their own role.
Route::get('/dashboard', function (Request $request) {
    return match (true) {
        $request->user()->isSuperAdmin() => redirect()->route('admin.dashboard'),
        $request->user()->isDispatcher() => redirect()->route('dispatch.dashboard'),
        // No dedicated dashboard of its own — a restaurant admin only
        // ever manages the one restaurant it's linked to, so its show
        // page (menu + profile) IS the dashboard.
        $request->user()->isRestaurantAdmin() => redirect()->route('admin.restaurants.show', $request->user()->restaurant_id),
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

        // Expenses: create + list only (append-only, see ExpenseController's
        // own docblock) — no edit/destroy routes exist.
        Route::get('/expenses', [AdminExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/create', [AdminExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [AdminExpenseController::class, 'store'])->name('expenses.store');
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

        // Must precede the resource route below — otherwise
        // GET /admin/customers/search would be swallowed by customers.show's
        // GET /admin/customers/{customer}.
        Route::get('/customers/search', [AdminCustomerController::class, 'search'])->name('customers.search');
        Route::resource('customers', AdminCustomerController::class);

        Route::resource('orders', AdminOrderController::class)->except('destroy');
        Route::patch('/orders/{order}/assign', [AdminOrderController::class, 'assign'])->name('orders.assign');
        Route::patch('/orders/{order}/transition', [AdminOrderController::class, 'transition'])->name('orders.transition');

        // Not restaurant-scoped: one account-wide "which weekday am I
        // previewing" preference, reused on every restaurant show page
        // — see RestaurantStatusPreviewController's own docblock for why
        // it lives in this role group rather than the one below (a
        // RESTAURANT_ADMIN never sees the picker that posts here).
        Route::patch('/restaurants-status-preview', [AdminRestaurantStatusPreviewController::class, 'update'])->name('restaurants.status-preview.update');
    });

// Restaurant & Menu Management: same as above, but also open to
// RESTAURANT_ADMIN — a separate group (rather than just adding the role
// to the one above) because a restaurant admin must NOT reach
// drivers/customers/orders, only its own restaurant's menu and profile.
// Route-level access here is deliberately coarse (any of the three roles
// gets past this middleware); RestaurantPolicy/MenuCategoryPolicy/
// MenuItemPolicy do the real per-restaurant ownership check inside each
// controller action — see their own docblocks.
Route::middleware(['auth', 'verified', 'role:dispatcher,super_admin,restaurant_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('restaurants', AdminRestaurantController::class);
        // Shallow: create/store stay nested under restaurants/{restaurant}
        // (need to know which restaurant), edit/update/destroy flatten to
        // just the child's own id (the model already knows its
        // restaurant). No index/show for either — a restaurant's own
        // `show` page is the de-facto index, listing categories with
        // their items inline.
        Route::resource('restaurants.menu-categories', AdminMenuCategoryController::class)
            ->shallow()->except(['index', 'show']);
        Route::resource('restaurants.menu-items', AdminMenuItemController::class)
            ->shallow()->except(['index', 'show']);
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

        // The shared board — every driver's primary "find work" page now.
        // /driver/offers below still exists and still fully works; it's
        // just no longer where the nav sends drivers. See
        // Driver\AvailableOrdersController's own docblock.
        Route::get('/available-orders', [DriverAvailableOrdersController::class, 'index'])->name('orders.available');
        Route::post('/available-orders/{order}/claim', [DriverAvailableOrdersController::class, 'claim'])->name('orders.available.claim');

        Route::get('/offers', [DriverOrderOfferController::class, 'index'])->name('offers.index');
        Route::patch('/offers/{offer}/accept', [DriverOrderOfferController::class, 'accept'])->name('offers.accept');
        Route::patch('/offers/{offer}/reject', [DriverOrderOfferController::class, 'reject'])->name('offers.reject');

        Route::get('/orders', [DriverOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [DriverOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/transition', [DriverOrderController::class, 'transition'])->name('orders.transition');
        Route::patch('/orders/{order}/release', [DriverOrderController::class, 'release'])->name('orders.release');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
